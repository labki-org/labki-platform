# Production Deployment Checklist

Use this checklist when deploying Labki to production.

## Pre-Deployment

### Security
- [ ] Change default admin password in `secrets.env`
- [ ] Use strong, unique `MARIADB_ROOT_PASSWORD`
- [ ] Use strong, unique `MW_DB_PASSWORD`
- [ ] Set `MW_SERVER` to your actual domain (with HTTPS)
- [ ] Ensure `$wgShowExceptionDetails = false` (default)

### Network
- [ ] Configure reverse proxy for HTTPS termination (Caddy recommended - see `docker-compose.caddy.yml`)
- [ ] Set `SITE_DOMAIN` in `.env` to your domain
- [ ] TLS/SSL certificates (automatic with Caddy + Let's Encrypt)
- [ ] Configure firewall to only expose ports 80/443
- [ ] Verify DNS A record points to server IP

### Database
- [ ] **External DB recommended** for production (AWS RDS, Cloud SQL, etc.)
- [ ] Configure automated database backups
- [ ] Test database restore procedure

## Deployment

```bash
# 1. Clone runtime repo
git clone https://github.com/labki-org/labki.git
cd labki

# 2. Configure secrets
cp config/secrets.env.example config/secrets.env
# Edit with production values

# 3. Pin to a stable version
echo "LABKI_VERSION=1.0.0" > .env

# 4. Start services
docker compose up -d
```

## Post-Deployment

### Verify
- [ ] Access wiki at your domain
- [ ] Log in as admin
- [ ] Create a test page
- [ ] Upload a test file

### Backup Setup
- [ ] Configure scheduled database dumps (see [Backup & Restore](backup-restore.md))
- [ ] Configure image directory backups
- [ ] Test restore procedure

### Monitoring
- [ ] Set up uptime monitoring (UptimeRobot, Pingdom)
- [ ] Configure log aggregation (optional)
- [ ] Set up alerting for container restarts

## Maintenance

### Updates
```bash
# Pull latest version
./update.sh

# Or pin to specific version
echo "LABKI_VERSION=1.1.0" > .env
./update.sh
```

### Logs
```bash
docker compose logs -f wiki
docker compose logs -f db
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Container keeps restarting | Check `docker compose logs wiki` for errors |
| Database connection failed | Verify `MW_DB_*` variables match database config |
| Extension not loading | Check PHP syntax in `LocalSettings.user.php` |
| Upload failures | Check permissions on `images/` directory |
