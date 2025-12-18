#!/usr/bin/env bash
set -euo pipefail

# This is the initial entrypoint skeleton.
# It will be expanded in Phase 4 to handle DB waiting, installation, and updates.

echo "[entrypoint] Labki Platform Starting..."

# Ensure LocalSettings.php exists if not mounted
# In the final implementation, this will include the bootstrap.php
if [ ! -f /var/www/html/LocalSettings.php ]; then
  echo "[entrypoint] Warning: No LocalSettings.php found. This is expected in Phase 2 before bootstrap is implemented."
  # For now, we don't block, so the user can see the container is running.
fi

# Execute the CMD (apache2-foreground)
exec "$@"
