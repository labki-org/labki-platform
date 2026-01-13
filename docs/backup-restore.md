# Backup & Restore Guide

This guide covers backing up and restoring a Labki MediaWiki instance.

## What to Backup

| Component | Location | Method |
|-----------|----------|--------|
| **Database** | MariaDB container | `mysqldump` |
| **Uploads** | `./images/` | File copy |
| **Configuration** | `./config/` | File copy |
| **User Extensions** | `./mw-user-extensions/` | File copy |

## Backup Procedures

### Database Backup

```bash
# Create a timestamped backup
docker compose exec db mysqldump -u labki -p$MW_DB_PASSWORD labki \
  > backup-$(date +%Y%m%d-%H%M%S).sql
```

**Automated backups** (add to crontab):
```bash
# Daily backup at 2 AM
0 2 * * * cd /path/to/labki && docker compose exec -T db mysqldump -u labki -plabki_pass labki > backups/db-$(date +\%Y\%m\%d).sql
```

### File Backups

```bash
# Backup uploads and config
tar -czf labki-files-$(date +%Y%m%d).tar.gz images/ config/ mw-user-extensions/
```

### Full Backup Script

Create `backup.sh`:
```bash
#!/bin/bash
set -e
BACKUP_DIR="./backups/$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Database
docker compose exec -T db mysqldump -u labki -p"$MW_DB_PASSWORD" labki > "$BACKUP_DIR/database.sql"

# Files
cp -r images/ "$BACKUP_DIR/"
cp -r config/ "$BACKUP_DIR/"
cp -r mw-user-extensions/ "$BACKUP_DIR/" 2>/dev/null || true

echo "Backup complete: $BACKUP_DIR"
```

## Restore Procedures

### Database Restore

```bash
# Stop wiki container first
docker compose stop wiki

# Restore database
cat backup.sql | docker compose exec -T db mysql -u labki -p$MW_DB_PASSWORD labki

# Restart wiki
docker compose start wiki
```

### File Restore

```bash
# Restore uploads
tar -xzf labki-files-YYYYMMDD.tar.gz

# Fix permissions
sudo chown -R 33:33 images/  # www-data UID
```

### Full Restore from Scratch

```bash
# 1. Clone repo
git clone https://github.com/labki-org/labki.git
cd labki

# 2. Restore config
cp /path/to/backup/config/* config/

# 3. Start database only
docker compose up -d db
sleep 10

# 4. Restore database
cat /path/to/backup/database.sql | docker compose exec -T db mysql -u labki -p$MW_DB_PASSWORD labki

# 5. Restore files
cp -r /path/to/backup/images/* images/
cp -r /path/to/backup/mw-user-extensions/* mw-user-extensions/

# 6. Start wiki
docker compose up -d wiki
```

## Retention Policy

Recommended backup retention:
- **Daily backups**: Keep 7 days
- **Weekly backups**: Keep 4 weeks
- **Monthly backups**: Keep 12 months

## Offsite Storage

For production, store backups offsite:
- AWS S3 / Google Cloud Storage
- Backblaze B2
- rsync to remote server

Example S3 upload:
```bash
aws s3 cp "$BACKUP_DIR" s3://your-bucket/labki-backups/ --recursive
```
