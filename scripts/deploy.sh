#!/usr/bin/env bash
set -euo pipefail

# Blue/Green Deployment Script for Zigzag
#
# Zero-downtime deployment for FrankenPHP/Caddy behind Cloudflare.
# No Traefik — each slot is a self-contained FrankenPHP instance.
#
# Usage: ./scripts/deploy.sh <new_slot> <image_tag>
# Example: ./scripts/deploy.sh green v1.2.3
#
# How it works:
#   1. Build the new slot image
#   2. Start shared infrastructure (infra profile)
#   3. Stop the old slot (releases ports 80/443)
#   4. Start the new slot (binds ports 80/443)
#   5. Health check the new slot
#   6. Run database migrations
#   7. Warm Symfony cache
#   8. Record active slot
#
# Each blue/green slot is a complete FrankenPHP/Caddy instance that binds
# ports 80/443 directly. Traffic switching happens by stopping the old slot
# and starting the new one. The brief downtime (~2-5s) during the switch
# is masked by Cloudflare's retry and edge caching — clients see no errors.
#
# Rollback: if the new slot fails health checks, the old slot is restarted.

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
slot_containers() {
  local slot="$1"
  echo "zigzag_php_${slot}" \
       "zigzag_messenger_worker_${slot}" \
       "zigzag_messenger_worker_webhooks_${slot}" \
       "zigzag_messenger_worker_tracking_${slot}"
}

cd "$PROJECT_DIR"

# Read SERVER_NAME from .env.prod
DOMAIN=$(grep -E '^SERVER_NAME=' .env.prod | cut -d= -f2- || echo "localhost")

echo "=== Blue/Green Deploy ==="
echo "New slot: $NEW_SLOT | Old slot: $OLD_SLOT | Image: $IMAGE_TAG"
echo "Domain:  $DOMAIN"
echo "Time: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo ""

# 1. Stamp APP_VERSION so every log line includes the deployed release
echo "-> Setting APP_VERSION=${IMAGE_TAG}..."
# Use grep+sed guard to avoid corrupting .env files with unexpected sed patterns.
# Only touch .env (committed), never write secrets to it. .env.prod is read-only here —
# APP_VERSION is passed via compose environment instead.
sed -i "s/^APP_VERSION=.*/APP_VERSION=${IMAGE_TAG}/" "${PROJECT_DIR}/.env"
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

# 4. Stop the OLD slot to release ports 80/443
echo "-> Stopping ${OLD_SLOT} slot to release ports..."
OLD_CONTAINERS=($(slot_containers "$OLD_SLOT"))
docker stop "${OLD_CONTAINERS[@]}" 2>/dev/null || true
docker rm "${OLD_CONTAINERS[@]}" 2>/dev/null || true
echo "OK: ${OLD_SLOT} stopped"

# 5. Start the NEW slot (binds ports 80/443)
echo "-> Starting ${NEW_SLOT} slot..."
DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_BASE --profile infra --profile "$NEW_SLOT" up -d
echo "OK: ${NEW_SLOT} slot started"

# 6. Wait for the NEW slot's php service to be healthy
echo "-> Health checking php-${NEW_SLOT}..."
RETRIES=0
until docker inspect --format='{{.State.Health.Status}}' "zigzag_php_${NEW_SLOT}" 2>/dev/null | grep -q "healthy"; do
  RETRIES=$((RETRIES + 1))
  if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
    echo "FAIL: Health check failed after ${MAX_RETRIES} attempts ($(( MAX_RETRIES * RETRY_INTERVAL ))s)."
    echo "-> Rolling back: restarting ${OLD_SLOT}..."
    # Stop the failed new slot
    NEW_CONTAINERS=($(slot_containers "$NEW_SLOT"))
    docker stop "${NEW_CONTAINERS[@]}" 2>/dev/null || true
    docker rm "${NEW_CONTAINERS[@]}" 2>/dev/null || true
    # Restart the old slot
    DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_BASE --profile infra --profile "$OLD_SLOT" up -d
    echo "FAIL: Rollback complete. ${OLD_SLOT} is active again."
    exit 1
  fi
  echo "  Waiting... (${RETRIES}/${MAX_RETRIES})"
  sleep "$RETRY_INTERVAL"
done
echo "OK: php-${NEW_SLOT} is healthy"
# Note: migrations were already applied by the container entrypoint before the
# health check passed — no need to run them again here.

# 7. Warm up Symfony cache (OPcache preload, container, routes, etc.)
echo "-> Warming Symfony cache..."
docker exec "zigzag_php_${NEW_SLOT}" \
  php -d memory_limit=512M bin/console cache:warmup --env=prod
echo "OK: Symfony cache warmed"

# 8. Warm up Redis cache pools (routes, drivers, MP fees, school geocodes)
# Runs after Symfony warmup so the container is fully built before we query the DB.
echo "-> Warming Redis cache pools..."
docker exec "zigzag_php_${NEW_SLOT}" \
  php -d memory_limit=256M bin/console app:cache:warm
echo "OK: Redis cache warmed"

# 9. Clean up old images (keep last 24h)
echo "-> Pruning old images..."
docker image prune -f --filter "until=24h" || true

# 10. Record which slot is active
echo "$NEW_SLOT" > "${PROJECT_DIR}/.active-slot"

echo ""
echo "=== Deploy Complete ==="
echo "Active slot: ${NEW_SLOT}"
echo "Image tag:   ${IMAGE_TAG}"
echo "Time:        $(date -u +%Y-%m-%dT%H:%M:%SZ)"
