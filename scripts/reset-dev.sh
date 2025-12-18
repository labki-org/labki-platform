#!/usr/bin/env bash
set -euo pipefail

# This script is intended to be run from the HOST, not inside the container.
# However, for checking logic, we can also have a version that runs inside.
# But "resetting" implies deleting external volumes.
# The plan said "scripts/reset-dev.sh" for the platform repo.

echo "To reset the dev environment:"
echo "1. docker compose down"
echo "2. docker volume rm labki_platform_db-data"
echo "3. rm -rf images/*"
echo "4. docker compose up -d"

# If we want to automate this, we assume we are in the root of the repo.
if [ -f "compose/docker-compose.dev.yml" ]; then
    read -p "Are you sure you want to delete the DB and uploads? (y/N) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker compose -f compose/docker-compose.dev.yml down
        # We need to know the volume name. It depends on the dir name usually.
        # Assuming typical docker-compose naming.
        # Better to let user handle volume deletion or use strict names.
        echo "Please verify volume deletion manually."
        echo "Run: docker volume ls | grep labki"
    fi
fi
