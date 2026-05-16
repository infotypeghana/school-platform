# SchoolMS Ghana — Deployment Risk Analysis

**Generated:** 2026-05-16  
**Environment:** VPS (Ubuntu 24.04) or Docker Compose  
**Deployment method:** CI/CD via GitHub Actions (SSH deploy on `main` push)

---

## Risk Matrix

| Risk | Probability | Impact | Rating | Mitigation |
|---|---|---|---|---|
| Database migration failure | Low | Critical | HIGH | Pre-deploy backup; `--pretend` dry-run |
| Environment variable missing | Medium | Critical | HIGH | `.env.example` validation in CI |
| Zero-downtime deploy failure | Medium | High | HIGH | Atomic symlink deploy or rolling restart |
| Redis connection failure | Low | High | MEDIUM | Redis Sentinel (Phase 1); health check alert |
| Queue jobs lost during deploy | Medium | Medium | MEDIUM | Graceful Horizon shutdown before restart |
| Webhook processing gap during deploy | Low | Medium | MEDIUM | Paystack retries; idempotency key prevents doubles |
| S3 credential rotation | Low | Medium | MEDIUM | IAM role preferred over static keys |
| PHP version mismatch | Low | High | MEDIUM | Docker pins `php:8.4-fpm-alpine` |
| SSL certificate expiry | Low | Critical | MEDIUM | Let's Encrypt auto-renew via certbot timer |
| Storage disk full | Medium | High | MEDIUM | `/health` storage check; backup rotation |

---

## 1. Pre-Deployment Checklist (Risk Mitigations)

### 1.1 — Environment Validation

Before every production deploy, verify:

```bash
# Required variables — deploy MUST fail if any are missing
for var in APP_KEY APP_URL APP_DOMAIN DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD \
           REDIS_HOST SESSION_DRIVER QUEUE_CONNECTION CACHE_STORE \
           PAYSTACK_SECRET_KEY MOOLRE_PUBLIC_KEY; do
  if [ -z "${!var}" ]; then echo "MISSING: $var"; exit 1; fi
done
```

**CI gate:** The GitHub Actions `deploy` job runs only after `tests` and `security` jobs pass.

### 1.2 — Database Migration Safety

**Risk:** A destructive migration (DROP COLUMN, rename) can be irreversible.

**Mitigation procedure:**
```bash
# 1. Take backup immediately before migrating
php artisan db:backup

# 2. Preview migration changes
php artisan migrate --pretend

# 3. Run migration
php artisan migrate --force

# 4. Verify row counts haven't changed unexpectedly
```

**Rollback:** All migrations must have a `down()` method. Tested in `php artisan migrate:rollback --pretend` before production deploy.

### 1.3 — Zero-Downtime Deploy

**Current risk:** `php artisan config:cache` during deploy causes a ~200ms window where config is stale.

**Mitigation (Deployer / atomic symlink pattern):**
```
/var/www/
  schoolms/                 ← current symlink
  releases/
    20260516-120000/        ← new release (prepare offline)
    20260515-090000/        ← previous release (keep for rollback)
```

Steps:
1. Clone new release to `releases/TIMESTAMP/`
2. Copy `.env` from shared folder
3. `composer install --no-dev` in new release dir
4. `php artisan config:cache && route:cache && view:cache` in new release dir
5. `php artisan migrate --force` against live DB
6. Atomically swap symlink: `ln -sfn releases/TIMESTAMP schoolms`
7. `php artisan horizon:terminate` → Horizon auto-restarts via Supervisor

**Rollback:** Re-point symlink to previous release — takes <5 seconds.

### 1.4 — Horizon Graceful Shutdown

Horizon workers must finish current jobs before the process is killed:

```bash
# Signal Horizon to terminate after current jobs finish (up to 60s)
php artisan horizon:terminate
# Supervisor restarts Horizon with new code
```

**Risk without this:** In-flight PDF generation or email jobs are killed mid-execution, leaving `status=processing` with no completion. The `GenerateReportCardJob` has 180s timeout — a hard kill would leave orphaned jobs.

**Mitigation:** The CI/CD deploy script calls `horizon:terminate` before restarting PHP-FPM.

---

## 2. Critical Failure Scenarios

### Scenario A — Migration Fails Midway

**Trigger:** Network loss during `php artisan migrate` or out-of-disk on DB server.

**Impact:** Schema is partially applied. Application errors on queries hitting the partial schema.

**Recovery:**
1. Restore from pre-deploy backup (taken in step 1.2)
2. Revert deploy symlink
3. Fix migration locally, test, re-deploy

**Time to recover:** 15–30 minutes (depends on DB size)

### Scenario B — Redis Unavailable at Deploy Time

**Trigger:** Redis server OOM, crash, or network partition.

**Impact:** Sessions fail (all users logged out), cache misses (DB overloaded), queue jobs pile up.

**Recovery:**
1. Restart Redis: `systemctl restart redis`
2. Health check `/health` will go green automatically
3. Queue jobs resume from Redis streams

**Fallback option:** Switch `SESSION_DRIVER=database` temporarily (`php artisan session:table && migrate`). Not recommended for production — just a bridge.

### Scenario C — APP_KEY Changed Accidentally

**Trigger:** Running `php artisan key:generate` on a live system.

**Impact:** All existing sessions invalidated (all users logged out). Encrypted data in DB becomes unreadable (including any session-stored data).

**Prevention:** `php artisan key:generate` is disabled in the CI/CD deploy script. Key is only set during initial install via `php artisan schoolms:install`.

### Scenario D — S3 Credentials Expired

**Trigger:** IAM key rotation, accidental revocation.

**Impact:** Logo uploads fail (silent fallback to placeholder), backup cloud upload fails (local backups still work), export ZIP downloads from S3 fail.

**Detection:** `/health` endpoint checks storage write. Backup failure triggers `BackupFailedNotification` email.

**Recovery:** Update `AWS_ACCESS_KEY_ID` + `AWS_SECRET_ACCESS_KEY` in `.env`, run `php artisan config:cache`.

---

## 3. Rollback Procedures

### Application Rollback
```bash
# Revert to previous release (atomic, <5s)
ln -sfn /var/www/releases/PREV_TIMESTAMP /var/www/schoolms
php artisan horizon:terminate
```

### Database Rollback
```bash
# Revert last migration batch
php artisan migrate:rollback

# Or restore full backup
gunzip -c /var/app/backups/db_TIMESTAMP.sql.gz | mysql -u root -p schoolms
```

### Docker Rollback
```bash
# Pull and run previous image tag
docker compose down
docker compose up -d --scale app=0
docker tag schoolms:PREV_TAG schoolms:latest
docker compose up -d
```

---

## 4. Deploy Risk by Environment

| Environment | Risk Level | Approvals Required |
|---|---|---|
| Local | None | None |
| Staging | Low | PR review |
| Production | Medium | CI green + manual approve (GitHub environment gate) |

The `.github/workflows/ci.yml` `deploy` job has `environment: production` — GitHub requires a manual approval from a repo admin before the SSH deploy runs.

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
