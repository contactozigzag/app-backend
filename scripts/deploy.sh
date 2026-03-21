#!/usr/bin/env bash
set -euo pipefail

# Blue/Green Deployment Script for Zigzag
#
# Usage: ./scripts/deploy.sh <new_slot> <image_tag>
# Example: ./scripts/deploy.sh green v1.2.3

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
COMPOSE_CMD="docker compose -f compose.yaml -f compose.deploy.yaml --env-file .env.prod"
MAX_RETRIES=30
RETRY_INTERVAL=5

cd "$PROJECT_DIR"

echo "=== Blue/Green Deploy ==="
echo "New slot: $NEW_SLOT | Old slot: $OLD_SLOT | Image: $IMAGE_TAG"
echo "Time: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo ""

# 1. Build the prod image with the deploy tag
echo "-> Building image for php-${NEW_SLOT}..."
DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_CMD build --no-cache "php-${NEW_SLOT}"
echo "OK: Image built"

# 2. Ensure shared infrastructure is running
echo "-> Starting shared infrastructure..."
$COMPOSE_CMD --profile infra up -d --no-recreate
echo "OK: Infrastructure running"

# 3. Start the NEW slot
echo "-> Starting ${NEW_SLOT} slot..."
DEPLOY_TAG="$IMAGE_TAG" $COMPOSE_CMD --profile "$NEW_SLOT" up -d
echo "OK: ${NEW_SLOT} slot started"

# 4. Wait for the NEW slot's php service to be healthy
echo "-> Health checking php-${NEW_SLOT}..."
RETRIES=0
until docker inspect --format='{{.State.Health.Status}}' "zigzag_php_${NEW_SLOT}" 2>/dev/null | grep -q "healthy"; do
  RETRIES=$((RETRIES + 1))
  if [ "$RETRIES" -ge "$MAX_RETRIES" ]; then
    echo "FAIL: Health check failed after ${MAX_RETRIES} attempts ($(( MAX_RETRIES * RETRY_INTERVAL ))s). Rolling back..."
    $COMPOSE_CMD --profile "$NEW_SLOT" down
    echo "FAIL: Rollback complete. ${OLD_SLOT} is still active."
    exit 1
  fi
  echo "  Waiting... (${RETRIES}/${MAX_RETRIES})"
  sleep "$RETRY_INTERVAL"
done
echo "OK: php-${NEW_SLOT} is healthy"

# 5. Run database migrations from the new slot
echo "-> Running migrations..."
docker compose -f compose.yaml -f compose.deploy.yaml exec "php-${NEW_SLOT}" \
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
echo "OK: Migrations complete"

# 6. Warm up Symfony cache
echo "-> Warming cache..."
docker compose -f compose.yaml -f compose.deploy.yaml exec "php-${NEW_SLOT}" \
  php bin/console cache:warmup --env=prod
echo "OK: Cache warmed"

# 7. Switch Traefik routing via dynamic file provider
# Traefik watches this file and picks up changes within 1-2 seconds
echo "-> Switching Traefik routing to ${NEW_SLOT}..."

SERVER_NAME="${SERVER_NAME:-localhost}"

cat > "${PROJECT_DIR}/traefik/dynamic/routing.yaml" << EOF
http:
  services:
    zigzag-app:
      loadBalancer:
        servers:
          - url: "http://php-${NEW_SLOT}:80"

  routers:
    zigzag-app:
      rule: "Host(\`${SERVER_NAME}\`)"
      service: zigzag-app
      entrypoints:
        - web
        - websecure
      tls:
        certResolver: letsencrypt

    zigzag-app-http-redirect:
      rule: "Host(\`${SERVER_NAME}\`)"
      service: zigzag-app
      entrypoints:
        - web
      middlewares:
        - redirect-to-https

    zigzag-mercure:
      rule: "Host(\`${SERVER_NAME}\`) && PathPrefix(\`/.well-known/mercure\`)"
      service: zigzag-app
      entrypoints:
        - web
        - websecure
      tls:
        certResolver: letsencrypt
      middlewares:
        - mercure-headers
        - mercure-buffering

  middlewares:
    redirect-to-https:
      redirectScheme:
        scheme: https
        permanent: true

    mercure-headers:
      headers:
        customResponseHeaders:
          X-Accel-Buffering: "no"

    mercure-buffering:
      buffering:
        maxResponseBodyBytes: 0
EOF

echo "OK: Traefik routing switched to ${NEW_SLOT}"

# 8. Grace period — let in-flight requests to the old slot complete
echo "-> Draining old slot (${OLD_SLOT})... waiting 30s"
sleep 30

# 9. Stop the old slot
echo "-> Stopping ${OLD_SLOT} slot..."
$COMPOSE_CMD --profile "$OLD_SLOT" down || true
echo "OK: ${OLD_SLOT} stopped"

# 10. Clean up old images (keep last 24h)
echo "-> Pruning old images..."
docker image prune -f --filter "until=24h" || true

# 11. Record which slot is active
echo "$NEW_SLOT" > "${PROJECT_DIR}/.active-slot"

echo ""
echo "=== Deploy Complete ==="
echo "Active slot: ${NEW_SLOT}"
echo "Image tag:   ${IMAGE_TAG}"
echo "Time:        $(date -u +%Y-%m-%dT%H:%M:%SZ)"
