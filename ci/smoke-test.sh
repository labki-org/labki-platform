#!/usr/bin/env bash
set -euo pipefail

# Smoke Test Script
# 1. Builds the image
# 2. Starts the stack
# 3. Waits for success
# 4. Cleans up
#
# Usage: ./smoke-test.sh [target]
#   target: 'prod' (default) or 'dev'

export DOCKER_TARGET="${1:-prod}"

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(dirname "$SCRIPT_DIR")
COMPOSE_FILE="$REPO_ROOT/compose/docker-compose.dev.yml"

echo "[smoke-test] Building image (target: $DOCKER_TARGET)..."
docker compose -f "$COMPOSE_FILE" build

echo "[smoke-test] Starting stack..."
docker compose -f "$COMPOSE_FILE" up -d

echo "[smoke-test] Waiting for wiki to be ready..."
# We can poll localhost:8080
MAX_RETRIES=30
count=0
success=false

while [ $count -lt $MAX_RETRIES ]; do
    if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/wiki/Main_Page | grep -q "200"; then
        echo "[smoke-test] Wiki is responding (HTTP 200)!"
        success=true
        break
    fi
    echo "[smoke-test] Waiting... ($count/$MAX_RETRIES)"
    sleep 2
    count=$((count+1))
done

# Dump logs on failure
if [ "$success" = false ]; then
    echo "[smoke-test] FAILURE: Wiki did not respond in time."
    docker compose -f "$COMPOSE_FILE" logs
    docker compose -f "$COMPOSE_FILE" down -v
    exit 1
fi

echo "[smoke-test] SUCCESS. Tearing down..."
docker compose -f "$COMPOSE_FILE" down -v
exit 0
