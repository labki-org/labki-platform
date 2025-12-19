#!/usr/bin/env bash
set -euo pipefail

# Installs MediaWiki using the standard installer.
# This is only called if the database is found to be empty.

echo "[install-mediawiki] Starting installation..."

# LocalSettings.php must not exist for install.php to run.
# We temporarily move our loader aside.
if [ -f /var/www/html/LocalSettings.php ]; then
    mv /var/www/html/LocalSettings.php /var/www/html/LocalSettings.php.bak
fi

php /var/www/html/maintenance/install.php \
  --dbname="${MW_DB_NAME:-labki}" \
  --dbserver="${MW_DB_HOST:-db}" \
  --dbuser="${MW_DB_USER:-labki}" \
  --dbpass="${MW_DB_PASSWORD:-labki_pass}" \
  --server="${MW_SERVER:-http://localhost:8080}" \
  --scriptpath="${MW_SCRIPT_PATH:-}" \
  --lang="${MW_SITE_LANG:-en}" \
  --pass="${MW_ADMIN_PASS:-change-me}" \
  "${MW_SITE_NAME:-Labki}" \
  "${MW_ADMIN_USER:-admin}"

# Restore our loader (discarding whatever install.php might have written)
if [ -f /var/www/html/LocalSettings.php.bak ]; then
    mv /var/www/html/LocalSettings.php.bak /var/www/html/LocalSettings.php
fi

echo "[install-mediawiki] Installation complete."
