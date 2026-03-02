#!/usr/bin/env bash
set -euo pipefail

# Resets the local dev environment. Run from the repository root on the host.

COMPOSE_FILE="compose/docker-compose.dev.yml"

if [ ! -f "$COMPOSE_FILE" ]; then
    echo "Error: $COMPOSE_FILE not found. Run this script from the repository root."
    exit 1
fi

echo "This will destroy the dev database and uploads."
read -p "Are you sure? (y/N) " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    docker compose -f "$COMPOSE_FILE" down -v
    rm -rf images/*
    echo "Dev environment reset. Run 'docker compose -f $COMPOSE_FILE up -d' to restart."
else
    echo "Aborted."
fi
