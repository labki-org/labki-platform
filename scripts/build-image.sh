#!/usr/bin/env bash
set -euo pipefail

# Builds the Labki Platform image and tags it for local use.

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(dirname "$SCRIPT_DIR")

TAG="labki-wiki:latest"

echo "[build-image] Building $TAG from $REPO_ROOT..."

docker build \
    -f "$REPO_ROOT/docker/Dockerfile" \
    -t "$TAG" \
    "$REPO_ROOT"

echo "[build-image] Success! Image available as '$TAG'."
