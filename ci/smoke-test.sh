#!/usr/bin/env bash
set -euo pipefail

# Smoke Test Script
#
# 1. Builds the image
# 2. Starts the stack
# 3. Waits for HTTP 200 on Main_Page
# 4. Probes Main_Page and the siteinfo API for expected:
#    - default skin (vector-2022)
#    - platform skins (Vector, Citizen, Tweeki, chameleon)
#    - curated extensions (SMW etc.) — present if enabled, absent if disabled
#    - custom file extensions (pdf, docx, mp4, svg)
# 5. Tears down the stack
#
# Usage: ./smoke-test.sh [target] [extensions_mode]
#   target          : 'prod' (default) or 'dev'
#   extensions_mode : 'enabled' (default) or 'disabled'

export DOCKER_TARGET="${1:-prod}"
EXTENSIONS_MODE="${2:-enabled}"

case "$EXTENSIONS_MODE" in
    enabled)  export MW_DISABLE_PLATFORM_EXTENSIONS=0 ;;
    disabled) export MW_DISABLE_PLATFORM_EXTENSIONS=1 ;;
    *)
        echo "[smoke-test] FAILURE: extensions_mode must be 'enabled' or 'disabled' (got: $EXTENSIONS_MODE)"
        exit 2
        ;;
esac

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(dirname "$SCRIPT_DIR")
COMPOSE_FILE="$REPO_ROOT/compose/docker-compose.dev.yml"

dump_logs_and_teardown() {
    echo "[smoke-test] Dumping container logs:"
    docker compose -f "$COMPOSE_FILE" logs || true
    docker compose -f "$COMPOSE_FILE" down -v || true
}

fail() {
    echo "[smoke-test] FAILURE: $1"
    dump_logs_and_teardown
    exit 1
}

echo "[smoke-test] Building image (target: $DOCKER_TARGET, extensions: $EXTENSIONS_MODE)..."
docker compose -f "$COMPOSE_FILE" build

echo "[smoke-test] Starting stack..."
docker compose -f "$COMPOSE_FILE" up -d

echo "[smoke-test] Waiting for wiki to be ready..."
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
[ "$success" = true ] || fail "Wiki did not respond in time."

# --- Probe runtime config from inside the container ---
# We avoid the HTTP API for this because the wiki is private
# ($wgGroupPermissions['*']['read'] = false), so anonymous siteinfo
# queries return an 'error' response instead of 'query'. Running a
# Maintenance.php-based probe script gives us authoritative values
# straight from the bootstrapped MediaWiki context.
echo "[smoke-test] Probing runtime config inside the container..."
PROBE=$(docker compose -f "$COMPOSE_FILE" exec -T wiki php /opt/labki/scripts/probe-config.php) \
    || { echo "[smoke-test] probe output (stdout+stderr):"; echo "$PROBE"; fail "probe-config.php failed inside container."; }
echo "[smoke-test] Probe returned:"
echo "$PROBE" | sed 's/^/[smoke-test]   /'

extract() { echo "$PROBE" | grep "^$1=" | head -1 | cut -d= -f2-; }
contains_csv() { echo "$1" | tr ',' '\n' | grep -qx "$2"; }

DEFAULT_SKIN=$(extract DEFAULT_SKIN)
SKINS=$(extract SKINS)
FILE_EXTS=$(extract FILE_EXTENSIONS)
EXT_NAMES=$(extract EXTENSIONS)

echo "[smoke-test] Verifying default skin..."
[ "$DEFAULT_SKIN" = "vector-2022" ] \
    || fail "default skin is '$DEFAULT_SKIN', expected 'vector-2022'."

# All four platform skins should be present in both modes.
echo "[smoke-test] Verifying platform skins..."
for skin in vector citizen tweeki chameleon; do
    contains_csv "$SKINS" "$skin" || fail "skin '$skin' missing from probe."
done

# Custom file extensions come from LocalSettings.defaults.php which
# loads regardless of MW_DISABLE_PLATFORM_EXTENSIONS.
echo "[smoke-test] Verifying custom file extensions..."
for filetype in pdf docx mp4 svg; do
    contains_csv "$FILE_EXTS" "$filetype" || fail "file extension '$filetype' missing from probe."
done

# Curated extensions: present in 'enabled' mode, absent in 'disabled' mode.
if [ "$EXTENSIONS_MODE" = "enabled" ]; then
    echo "[smoke-test] Verifying curated extensions are loaded..."
    for ext in SemanticMediaWiki VisualEditor PageForms ConfirmAccount; do
        contains_csv "$EXT_NAMES" "$ext" || fail "extension '$ext' missing in 'enabled' mode."
    done
else
    echo "[smoke-test] Verifying curated extensions are skipped..."
    if contains_csv "$EXT_NAMES" "SemanticMediaWiki"; then
        fail "SemanticMediaWiki present despite MW_DISABLE_PLATFORM_EXTENSIONS=1."
    fi
fi

echo "[smoke-test] SUCCESS. Tearing down..."
docker compose -f "$COMPOSE_FILE" down -v
exit 0
