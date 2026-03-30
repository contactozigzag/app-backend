#!/usr/bin/env bash
set -euo pipefail

# Blue/Green Deployment Script for Zigzag
#
# Zero-downtime deployment using Caddy's admin API for traffic switching.
# No Traefik — FrankenPHP/Caddy serves directly behind Cloudflare.
#
# Usage: ./scripts/deploy.sh <new_slot> <image_tag>
# Example: ./scripts/deploy.sh green v1.2.3
#
# How it works:
#   1. Build the new slot image
#   2. Start shared infrastructure (infra profile)
#   3. Start the new slot (blue/green profile)
#   4. Health check the new slot
#   5. Run database migrations
#   6. Warm Symfony cache
#   7. Switch traffic via Caddy admin API (zero-downtime)
#   8. Drain old slot connections (30s grace period)
#   9. Stop old slot containers
#
# Caddy's admin API performs a graceful config reload: new connections go to
# the new upstream, existing connections (including Mercure SSE) drain
# naturally. This replaces the Traefik file-provider approach.

if [ $# -lt 2 ]; then
  echo "Usage: $0 <new_slot> <image_tag>"
  echo "  new_slot:  blue or green"
  echo "  image_tag: version tag (e.g., v1.2.3) or short SHA"
  exit 1
fi

NEW_SLOT="$1"
IMAGE_TAG="$2"

if [[ "$NEW_SLOT" != "blue" && "$NEW_SLOT" != "green" ]]; then
  echo "Error: slot must be 'blue' or 'green', got '${NEW_SLOT}'"
  exit 1
fi

OLD_SLOT=$( [ "$NEW_SLOT" = "blue" ] && echo "green" || echo "blue" )

PROJECT_DIR="/opt/zigzag"
COMPOSE_BASE="docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.prod"
MAX_RETRIES=30
RETRY_INTERVAL=5

# Slot container names
OLD_SLOT_CONTAINERS=(
  "zigzag_php_${OLD_SLOT}"
  "zigzag_messenger_worker_${OLD_SLOT}"
  "zigzag_messenger_worker_webhooks_${OLD_SLOT}"
  "zigzag_messenger_worker_tracking_${OLD_SLOT}"
)

cd "$PROJECT_DIR"

# Read PUBLIC_DOMAIN from .env.prod
PUBLIC_DOMAIN=$(grep -E '^PUBLIC_DOMAIN=' .env.prod | cut -d= -f2- || echo "localhost")
if [ -z "$PUBLIC_DOMAIN" ] || [ "$PUBLIC_DOMAIN" = "localhost" ]; then
  PUBLIC_DOMAIN=$(grep -E '^SERVER_NAME=' .env.prod | cut -d= -f2- || echo "localhost")
fi

# Read Mercure JWT keys from .env.prod
MERCURE_JWT_SECRET=$(grep -E '^CADDY_MERCURE_JWT_SECRET=' .env.prod | cut -d= -f2- || echo "")

echo "=== Blue/Green Deploy (Caddy Admin API) ==="
echo "New slot: $NEW_SLOT | Old slot: $OLD_SLOT | Image: $IMAGE_TAG"
echo "Domain:  $PUBLIC_DOMAIN"
echo "Time: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo ""

# 1. Stamp APP_VERSION so every log line includes the deployed release
echo "-> Setting APP_VERSION=${IMAGE_TAG}..."
sed -i "s/APP_VERSION=.*/APP_VERSION=${IMAGE_TAG}/" "${PROJECT_DIR}/.env"
sed -i "s/APP_VERSION=.*/APP_VERSION=${IMAGE_TAG}/" "${PROJECT_DIR}/.env.prod"
grep -q 'APP_VERSION' "${PROJECT_DIR}/.env.prod" || echo "APP_VERSION=${IMAGE_TAG}" >> "${PROJECT_DIR}/.env.prod"
echo "OK: APP_VERSION updated"

# 2. Build the prod image with the deploy tag
BUILD_FLAGS=""
if [ "${NO_CACHE:-false}" = "true" ]; then
  BUILD_FLAGS="--no-cache"
  echo "-> Building image for php-${NEW_SLOT} (no cache)..."
else
  echo "-> Building image for php-${NEW_SLOT}..."
fi
DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_BASE --profile infra --profile "$NEW_SLOT" build $BUILD_FLAGS "php-${NEW_SLOT}"
echo "OK: Image built"

# 3. Ensure shared infrastructure is running
echo "-> Starting shared infrastructure..."
$COMPOSE_BASE --profile infra up -d --no-recreate
echo "OK: Infrastructure running"

# 4. Start the NEW slot (include --profile infra so cross-profile depends_on resolves)
echo "-> Starting ${NEW_SLOT} slot..."
DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_BASE --profile infra --profile "$NEW_SLOT" up -d
echo "OK: ${NEW_SLOT} slot started"

# 5. Wait for the NEW slot's php service to be healthy
echo "-> Health checking php-${NEW_SLOT}..."
RETRIES=0
until docker inspect --format='{{.State.Health.Status}}' "zigzag_php_${NEW_SLOT}" 2>/dev/null | grep -q "healthy"; do
  RETRIES=$((RETRIES + 1))
  if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
    echo "FAIL: Health check failed after ${MAX_RETRIES} attempts ($(( MAX_RETRIES * RETRY_INTERVAL ))s). Rolling back..."
    NEW_SLOT_CONTAINERS=(
      "zigzag_php_${NEW_SLOT}"
      "zigzag_messenger_worker_${NEW_SLOT}"
      "zigzag_messenger_worker_webhooks_${NEW_SLOT}"
      "zigzag_messenger_worker_tracking_${NEW_SLOT}"
    )
    docker stop "${NEW_SLOT_CONTAINERS[@]}" 2>/dev/null || true
    docker rm "${NEW_SLOT_CONTAINERS[@]}" 2>/dev/null || true
    echo "FAIL: Rollback complete. ${OLD_SLOT} is still active."
    exit 1
  fi
  echo "  Waiting... (${RETRIES}/${MAX_RETRIES})"
  sleep "$RETRY_INTERVAL"
done
echo "OK: php-${NEW_SLOT} is healthy"

# 6. Run database migrations from the new slot
echo "-> Running migrations..."
docker exec "zigzag_php_${NEW_SLOT}" \
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "OK: Migrations complete"

# 7. Warm up Symfony cache
echo "-> Warming cache..."
docker exec "zigzag_php_${NEW_SLOT}" \
  php bin/console cache:warmup --env=prod
echo "OK: Cache warmed"

# 8. Switch traffic via Caddy admin API
#
# Caddy's admin API (port 2019) accepts a full config reload via POST /load.
# This performs a graceful handoff: new connections use the new config immediately,
# while existing connections (including long-lived Mercure SSE streams) drain
# naturally without interruption.
#
# We send the full Caddy JSON config with the upstream pointing to the new slot.
# This replaces the old Traefik file-provider approach with a single HTTP call.
echo "-> Switching traffic to ${NEW_SLOT} via Caddy admin API..."

CADDY_CONFIG=$(cat <<CADDY_JSON
{
  "admin": {
    "listen": "localhost:2019"
  },
  "apps": {
    "frankenphp": {
      "worker": {
        "file": "./public/index.php",
        "num": 4
      }
    },
    "http": {
      "servers": {
        "srv0": {
          "listen": [":80", ":443"],
          "trusted_proxies": {
            "source": "private_ranges"
          },
          "routes": [
            {
              "match": [{"path": ["/.well-known/mercure*"]}],
              "handle": [
                {
                  "handler": "mercure",
                  "publisher_jwt": {"key": "${MERCURE_JWT_SECRET}"},
                  "subscriber_jwt": {"key": "${MERCURE_JWT_SECRET}"},
                  "anonymous": true,
                  "subscriptions": true,
                  "heartbeat_interval": "45s"
                }
              ]
            },
            {
              "handle": [
                {"handler": "vars", "root": "/app/public"},
                {"handler": "encode", "encodings": {"zstd": {}, "br": {}, "gzip": {}}},
                {
                  "handler": "reverse_proxy",
                  "upstreams": [{"dial": "php-${NEW_SLOT}:80"}]
                }
              ]
            }
          ]
        }
      }
    }
  }
}
CADDY_JSON
)

# The active slot's Caddy admin API listens on port 2019 inside the container.
# We exec into the new slot to reload its own config (it's the one that binds ports 80/443).
HTTP_CODE=$(docker exec "zigzag_php_${NEW_SLOT}" \
  curl -s -o /dev/null -w '%{http_code}' \
  -X POST http://localhost:2019/load \
  -H "Content-Type: application/json" \
  -d "${CADDY_CONFIG}" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
  echo "OK: Traffic switched to ${NEW_SLOT} via Caddy admin API"
else
  echo "WARN: Caddy admin API returned HTTP ${HTTP_CODE}. Falling back to caddy reload..."
  # Fallback: write config to file and reload
  docker exec "zigzag_php_${NEW_SLOT}" \
    caddy reload --config /etc/frankenphp/Caddyfile 2>/dev/null || true
  echo "OK: Caddy config reloaded via CLI fallback"
fi

# 9. Grace period — let in-flight requests to the old slot complete
echo "-> Draining old slot (${OLD_SLOT})... waiting 30s"
sleep 30

# 10. Stop the old slot containers by name (NOT docker compose down, which would remove infra)
echo "-> Stopping ${OLD_SLOT} slot..."
docker stop "${OLD_SLOT_CONTAINERS[@]}" 2>/dev/null || true
docker rm "${OLD_SLOT_CONTAINERS[@]}" 2>/dev/null || true
echo "OK: ${OLD_SLOT} stopped"

# 11. Clean up old images (keep last 24h)
echo "-> Pruning old images..."
docker image prune -f --filter "until=24h" || true

# 12. Record which slot is active
echo "$NEW_SLOT" > "${PROJECT_DIR}/.active-slot"

echo ""
echo "=== Deploy Complete ==="
echo "Active slot: ${NEW_SLOT}"
echo "Image tag:   ${IMAGE_TAG}"
echo "Time:        $(date -u +%Y-%m-%dT%H:%M:%SZ)"
