#!/usr/bin/env bash
set -euo pipefail

# Installs MediaWiki using the standard installer.
# This is only called if the database is found to be empty.

echo "[install-mediawiki] Starting installation..."

php /var/www/html/maintenance/install.php \
  --dbname="${MW_DB_NAME:-labki}" \
  --dbserver="${MW_DB_HOST:-db}" \
  --dbuser="${MW_DB_USER:-labki}" \
  --dbpass="${MW_DB_PASSWORD:-labki_pass}" \
  --server="${MW_SERVER:-http://localhost:8080}" \
  --scriptpath="${MW_SCRIPT_PATH:-/w}" \
  --lang="${MW_SITE_LANG:-en}" \
  --pass="${MW_ADMIN_PASS:-change-me}" \
  "${MW_SITE_NAME:-Labki}" \
  "${MW_ADMIN_USER:-admin}"

echo "[install-mediawiki] Installation complete."
