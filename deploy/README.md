# SchoolMS Ghana — Deploy Configs

## Horizon Process Management

Pick **one** of the two approaches below depending on your server.

### Option A — Supervisor (Ubuntu/Debian with supervisord)

```bash
# 1. Copy config
sudo cp deploy/supervisor/schoolms-horizon.conf /etc/supervisor/conf.d/

# 2. Edit the path if your web root isn't /var/www/schoolms
sudo nano /etc/supervisor/conf.d/schoolms-horizon.conf

# 3. Load and start
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start schoolms-horizon:*

# Useful commands
sudo supervisorctl status
sudo supervisorctl restart schoolms-horizon:*
sudo supervisorctl tail -f schoolms-horizon stdout
```

### Option B — systemd (modern Ubuntu/Debian without supervisord)

```bash
# 1. Copy unit file
sudo cp deploy/systemd/schoolms-horizon.service /etc/systemd/system/

# 2. Edit the path if needed
sudo nano /etc/systemd/system/schoolms-horizon.service

# 3. Enable and start
sudo systemctl daemon-reload
sudo systemctl enable schoolms-horizon
sudo systemctl start schoolms-horizon

# Useful commands
sudo systemctl status schoolms-horizon
sudo journalctl -u schoolms-horizon -f        # live logs
sudo systemctl restart schoolms-horizon
```

## Cron Setup

Add to root crontab (`sudo crontab -e`):

```cron
* * * * * www-data php /var/www/schoolms/artisan schedule:run >> /dev/null 2>&1
```

This drives:
- `CheckSubscriptionStatusJob` — 06:00 WAT daily
- `SyncBiometricAttendance` — every 15 min between 06:00–18:00
- `horizon:snapshot` — every 5 min (Horizon metrics)
- `db:backup` — 02:00 WAT daily (database dump)

## After Deploy

```bash
cd /var/www/schoolms

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart Horizon so workers pick up new code
sudo supervisorctl restart schoolms-horizon:*
# or
sudo systemctl restart schoolms-horizon
```
