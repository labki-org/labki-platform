#!/usr/bin/env bash
set -euo pipefail

/opt/labki/scripts/wait-for-db.sh

echo "[jobrunner] Starting job runner loop..."

while true; do
    php /var/www/html/maintenance/runJobs.php --memory-limit 256M --maxjobs 100 --procs 1 --wait > /dev/null 2>&1
    sleep 1
done
