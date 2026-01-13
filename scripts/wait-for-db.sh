#!/usr/bin/env bash
set -euo pipefail

host="${MW_DB_HOST:-db}"
user="${MW_DB_USER:-labki}"
pass="${MW_DB_PASSWORD:-labki_pass}"

echo "[wait-for-db] Waiting for database at $host..."

for _ in {1..60}; do
  if MYSQL_PWD="$pass" mysql --ssl=0 --protocol=TCP -h "$host" -u "$user" -e "SELECT 1" >/dev/null 2>&1; then
    echo "[wait-for-db] Database is ready!"
    exit 0
  fi
  sleep 2
done

echo "[wait-for-db] Timeout waiting for database."
exit 1
