#!/usr/bin/env bash
set -euo pipefail

SOURCES_FILE="${1:-}"

if [ -z "$SOURCES_FILE" ]; then
    echo "Usage: $0 <sources.txt>"
    exit 1
fi

echo "Reading extensions from $SOURCES_FILE..."

while IFS=$'\t ' read -r name url ref type; do
    # Strip DOS carriage returns
    name="${name//$'\r'/}"
    url="${url//$'\r'/}"
    ref="${ref//$'\r'/}"
    type="${type//$'\r'/}"

    # Skip comments and empty lines
    [[ -z "$name" || "$name" == \#* ]] && continue

    if [ "$type" == "skin" ]; then
        target_dir="/var/www/html/skins/$name"
    else
        target_dir="/var/www/html/extensions/$name"
    fi

    if [ -d "$target_dir" ]; then
        echo "Skipping $name ($type), already exists."
    else
        echo "Cloning $name ($type) [$ref]..."
        git clone --depth=1 --branch "$ref" "$url" "$target_dir"
    fi

done < "$SOURCES_FILE"
