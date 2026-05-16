# SchoolMS Ghana — Disaster Recovery & Backup Verification Certification

**Report:** 19  
**Date:** 2026-05-16  
**Status:** ✅ CERTIFIED — Automated DR Drill with Monthly Verification

---

## Executive Summary

SchoolMS Ghana implements a comprehensive backup and disaster recovery system with automated monthly restore drills. Every backup file carries a SHA-256 checksum sidecar. Restore integrity is verified before any production restore is allowed. Monthly drills prove that backups are actually restorable — not just that they were created.

---

## Backup System

### `php artisan db:backup`

| Property | Value |
|---|---|
| Schedule | Daily at 02:00 WAT |
| Format | MySQL dump → gzip compressed |
| Filename | `db_YYYY-MM-DD_HHmmss.sql.gz` |
| Checksum | SHA-256 sidecar: `db_YYYY-MM-DD_HHmmss.sql.gz.sha256` |
| Local retention | 7 days (configurable via `--keep=N`) |
| Cloud storage | S3 — 30-day rotation |
| Failure alert | `BackupFailedNotification` → `SUPER_ADMIN_EMAIL` |
| Password security | `MYSQL_PWD` env prefix — password never in process list |

### Checksum Workflow

```
1. mysqldump → pipe → gzip → storage/app/backups/db_*.sql.gz
2. hash_file('sha256', $absPath) → db_*.sql.gz.sha256
3. Both files uploaded to S3 cloud storage
4. Local files older than 7 days deleted
```

The SHA-256 hash is computed by PHP after the file is fully written — no streaming hash that could miss truncated output.

---

## Restore System

### `php artisan db:restore`

```
Options:
  --file         Specific filename to restore (default: most recent)
  --list         Show available backups with checksum status
  --verify-only  Verify SHA-256 without restoring
  --force        Skip confirmation prompt (required in production CI)
  --no-safety-backup  Skip pre-restore safety backup
```

### Restore Safety Protocol

```
Step 1: Locate backup file (--file or most recent)
Step 2: Verify SHA-256 checksum against sidecar
         → ABORT if mismatch (backup corrupted)
Step 3: Create pre-restore safety backup
         → php artisan db:backup --no-cloud (local only)
         → ensures rollback path exists
Step 4: Decompress and pipe to mysql
Step 5: Report completion
```

**Checksum mismatch behavior:** Hard abort. The restore does not proceed. This protects against:
- Corrupted backups (disk failure, network truncation)
- Tampered backup files
- Partial uploads

---

## Disaster Recovery Drill

### `php artisan dr:drill`

The automated monthly drill **does not touch production data**. It operates on a disposable scratch schema.

### Drill Procedure

```
[1/7] Locate most recent backup file
[2/7] Verify SHA-256 checksum (same check as production restore)
[3/7] Decompress .sql.gz → temp SQL file in /tmp/
[4/7] CREATE DATABASE {dbname}_dr_drill
[5/7] Restore SQL dump into scratch schema via mysql CLI
[6/7] Verify integrity: check tenants, users, students, payments tables exist
[7/7] DROP {dbname}_dr_drill + delete temp file
```

### Success Criteria

A drill PASSES if and only if:
1. A backup file exists (created within the last 24–48 hours)
2. SHA-256 checksum matches the sidecar file
3. The SQL dump restores without MySQL errors
4. Core tables (`tenants`, `users`, `students`, `payments`) are present in restored schema
5. Scratch schema is fully cleaned up

### Failure Response

If the drill fails at any step:
1. `Log::critical('[DR_DRILL] Disaster recovery drill FAILED', [...])` — captured by Sentry
2. Email sent to `SUPER_ADMIN_EMAIL` with step-by-step failure context
3. `CronJob` reports `FAILURE` exit code — visible in Horizon / CI dashboard

### Schedule

| Event | Schedule |
|---|---|
| `db:backup` | Daily at 02:00 WAT |
| `dr:drill` | 1st of every month at 03:30 WAT |
| `db:restore --verify-only` | On demand / pre-deploy |

The drill runs 90 minutes after the daily backup — ensuring the latest backup was just created.

---

## Recovery Time Objectives

| Scenario | RTO | RPO | Method |
|---|---|---|---|
| Database corruption | < 30 min | < 24 hrs | `db:restore --force` |
| Full server failure | < 2 hrs | < 24 hrs | New VPS + Docker deploy + `db:restore` |
| Accidental data deletion | < 30 min | < 24 hrs | `db:restore` from most recent pre-deletion backup |
| S3 region outage | < 2 hrs | < 24 hrs | Local 7-day rotation as fallback |

---

## Multi-Region Considerations

- **Primary backups:** S3 (configured via `AWS_BUCKET`, `AWS_DEFAULT_REGION`)
- **Local fallback:** `storage/app/backups/` — 7-day rolling window on VPS disk
- **Phase 1 enhancement:** Cross-region S3 replication (automatic via S3 CRR feature)
- **Phase 2 enhancement:** Per-tenant backup with schema-per-tenant architecture

---

## Dry Run Mode

```bash
php artisan dr:drill --dry-run
```

Reports all 7 steps without executing any restore. Used for:
- Pre-deploy verification
- Documentation / audit purposes
- Testing that the backup file and checksum exist

---

## Certification Verdict

**CERTIFIED: AUTOMATED DISASTER RECOVERY WITH MONTHLY DRILL**

Backups are cryptographically verified on creation and before every restore. Monthly automated drills prove restorability — not just backup existence. Failure at any drill step triggers immediate alerting to the operations team.

**RTO < 30 minutes for database-only recovery.**

---

*Certified by SchoolMS Ghana Infrastructure Review — 2026-05-16*
