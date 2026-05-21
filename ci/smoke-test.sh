#!/usr/bin/env bash
set -euo pipefail

# Smoke Test Script
#
# 1. Builds the image
# 2. Starts the stack
# 3. Waits for HTTP 200 on Main_Page
# 4. Probes Main_Page and the siteinfo API for expected:
#    - default skin (vector-2022)
#    - platform skins (Vector, Citizen, Tweeki)
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
# Only start db + wiki. The jobrunner shares the wiki entrypoint and
# would race on install.php; that's exercised in production where
# `condition: service_healthy` makes the jobrunner wait for the wiki
# to be ready. The smoke test is about verifying wiki config, not
# job execution.
docker compose -f "$COMPOSE_FILE" up -d db wiki

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

# --- Verify platform ResourceLoader modules compile and serve ---
# A typo in skins.platform.php's $wgResourceModules registration, a
# broken localBasePath, or a syntax error in our LESS files would all
# produce HTTP 200 (the page still renders against default Tweeki),
# so the Main_Page check above can't catch them. ResourceLoader
# encodes the failure mode in the response body:
#   * unregistered module -> /* Problematic modules: { "x": "missing" } */
#   * LESS compile error  -> /* Less compile error: ... */
# Both are served with status 200, so we have to inspect the body.
verify_module() {
    local module=$1 only=$2 body
    body=$(curl -sS "http://localhost:8080/load.php?modules=$module&only=$only&skin=tweeki&debug=true") \
        || fail "ResourceLoader request for '$module' failed."
    [ -n "$body" ] || fail "ResourceLoader returned empty body for '$module'."
    case "$body" in
        *"\"$module\": \"missing\""*) fail "ResourceLoader module '$module' is not registered." ;;
        *"Less compile error"*)       fail "LESS compile error in module '$module'." ;;
    esac
}
echo "[smoke-test] Verifying platform ResourceLoader modules..."
verify_module skin.labki.tweeki.styles  styles
verify_module skin.labki.tweeki.scripts scripts
# Forum module is gated on extensions being loaded (it's registered in
# extensions.platform.php). Skip in 'disabled' mode where the file doesn't
# load and the module names are absent from ResourceLoader.
if [ "$EXTENSIONS_MODE" = "enabled" ]; then
    verify_module ext.discussionforum.styles styles
    verify_module ext.discussionforum        scripts
fi

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
NAMESPACES=$(extract NAMESPACES)
SMW_FORUM_PROPS=$(extract SMW_FORUM_PROPS)

echo "[smoke-test] Verifying default skin..."
[ "$DEFAULT_SKIN" = "tweeki" ] \
    || fail "default skin is '$DEFAULT_SKIN', expected 'tweeki'."

echo "[smoke-test] Verifying platform skins..."
for skin in vector citizen tweeki; do
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
    # Names match the value the extension registers, which is occasionally
    # the human-readable name rather than the directory name (e.g.
    # ConfirmAccount registers as "Confirm User Accounts"). Echo and
    # DiscussionTools are bundled with MW 1.44 but we still gate them
    # on the curated load so a deliberate disable doesn't go silent.
    for ext in SemanticMediaWiki VisualEditor PageForms "Confirm User Accounts" Echo DiscussionTools; do
        contains_csv "$EXT_NAMES" "$ext" || fail "extension '$ext' missing in 'enabled' mode."
    done
else
    echo "[smoke-test] Verifying curated extensions are skipped..."
    if contains_csv "$EXT_NAMES" "SemanticMediaWiki"; then
        fail "SemanticMediaWiki present despite MW_DISABLE_PLATFORM_EXTENSIONS=1."
    fi
fi

# Forum / Forum_talk namespaces (3000/3001) are declared by the dev overlay
# at compose/dev-config/LocalSettings.user.php — only mounted when the
# compose target is 'dev'. On 'prod' they're absent by design.
if [ "$DOCKER_TARGET" = "dev" ] && [ "$EXTENSIONS_MODE" = "enabled" ]; then
    echo "[smoke-test] Verifying forum namespaces are registered..."
    for ns in 3000 3001; do
        contains_csv "$NAMESPACES" "$ns" \
            || fail "namespace ID '$ns' missing from probe (dev overlay didn't load?)."
    done

    echo "[smoke-test] Verifying DiscussionForum SMW custom properties..."
    for pid in ___forum_subject ___forum_starter ___forum_comments ___forum_participants ___forum_parent; do
        contains_csv "$SMW_FORUM_PROPS" "$pid" \
            || fail "SMW custom property '$pid' is not registered (forum hook didn't fire?)."
    done

    # End-to-end probe: save a synthetic Forum_talk subpage, drain the
    # discussionForumAnnotate job (which parses Parsoid HTML via DT and runs
    # RefreshLinksJob inline), and verify all five SMW properties plus
    # DISPLAYTITLE / DEFAULTSORT landed correctly. Exercises the full
    # save → DT job → SMW write cycle that runs on every real topic save.
    echo "[smoke-test] Probing DiscussionForum save + DT-annotate-job cycle..."
    FORUM_PROBE=$(docker compose -f "$COMPOSE_FILE" exec -T wiki php /opt/labki/scripts/probe-forum-page.php) \
        || { echo "[smoke-test] forum-probe output:"; echo "$FORUM_PROBE"; fail "probe-forum-page.php failed inside container."; }
    echo "[smoke-test] Forum probe returned:"
    echo "$FORUM_PROBE" | sed 's/^/[smoke-test]   /'

    fextract() { echo "$FORUM_PROBE" | grep "^$1=" | head -1 | cut -d= -f2-; }

    [ "$(fextract DISPLAYTITLE)" = "Hello smoke" ] \
        || fail "DISPLAYTITLE is '$(fextract DISPLAYTITLE)', expected 'Hello smoke' (first H2 was not promoted)."
    [ "$(fextract DEFAULTSORT)" = "Forum talk:Smoketest/2026-01-01 120000 Bob" ] \
        || fail "DEFAULTSORT is '$(fextract DEFAULTSORT)', expected canonical title (SMW sortkey hijack regression)."
    [ "$(fextract FORUM_SUBJECT)" = "Hello smoke" ] \
        || fail "FORUM_SUBJECT SMW property is '$(fextract FORUM_SUBJECT)', expected 'Hello smoke'."
    [ "$(fextract FORUM_COMMENTS)" = "3" ] \
        || fail "FORUM_COMMENTS is '$(fextract FORUM_COMMENTS)', expected 3 (DT's ContentHeadingItem::getCommentCount of three signed comments under the H2)."
    [ "$(fextract FORUM_PARTICIPANTS)" = "2" ] \
        || fail "FORUM_PARTICIPANTS is '$(fextract FORUM_PARTICIPANTS)', expected 2 unique authors (DT's getAuthorsBelow)."
    [ "$(fextract FORUM_STARTER)" = "User:Bob" ] \
        || fail "FORUM_STARTER is '$(fextract FORUM_STARTER)', expected 'User:Bob' (DT's getOldestReply()->getAuthor — case-folding regression?)."
    [ "$(fextract FORUM_PARENT)" = "Forum:Smoketest" ] \
        || fail "FORUM_PARENT is '$(fextract FORUM_PARENT)', expected 'Forum:Smoketest' (talk-base -> subject derivation regression?)."
fi

echo "[smoke-test] SUCCESS. Tearing down..."
docker compose -f "$COMPOSE_FILE" down -v
exit 0
