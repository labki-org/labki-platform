#!/usr/bin/env bash
set -e

SOURCES_FILE=$1

if [ -z "$SOURCES_FILE" ]; then
    echo "Usage: $0 <sources.txt>"
    exit 1
fi

echo "Reading extensions from $SOURCES_FILE..."

# Read the file line by line, skipping comments and empty lines
while read -r name url ref type; do
    # Skip comments
    [[ "$name" =~ ^#.*$ ]] && continue
    # Skip empty lines
    [ -z "$name" ] && continue

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
