#!/usr/bin/env bash
set -euo pipefail

# Wait for DB first
/opt/labki/scripts/wait-for-db.sh

echo "[jobrunner] Starting job runner loop..."

# Infinite loop to run jobs
while true; do
    # Run jobs
    php /var/www/html/maintenance/runJobs.php --memory-limit 256M --maxjobs 100 --procs 1 --wait > /dev/null 2>&1
    
    # Wait a bit before checking again to avoid CPU spin if queue is empty/fast
    sleep 1
done
