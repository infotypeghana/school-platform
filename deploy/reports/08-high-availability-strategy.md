# SchoolMS Ghana — High Availability Strategy

**Generated:** 2026-05-16  
**Target SLA:** 99.9% uptime (Phase 0–1) → 99.95% (Phase 2)  
**Acceptable downtime:** 99.9% = 8.7 h/year; 99.95% = 4.4 h/year

---

## Single Points of Failure Analysis

| Component | Current State | SPOF? | HA Solution |
|---|---|---|---|
| PHP-FPM (app) | Single VPS | YES | 2nd node + LB (Phase 1) |
| MySQL | DO Managed (auto-failover) | NO | Managed handles it |
| Redis | DO Managed (multi-AZ) | NO | Managed handles it |
| Nginx | Single VPS | YES | Shared with app nodes |
| Horizon workers | Single process | YES | Supervisor auto-restarts |
| Scheduler | Single cron process | YES | Single-instance by design; acceptable |
| S3/Spaces | DO Managed | NO | Replicated by provider |
| DNS | Cloudflare | NO | Anycast, globally distributed |

**Phase 0 SPOF risk:** The single VPS is the primary SPOF. A VPS crash means full outage. Mitigation: DO Droplet SLA is 99.99% uptime; Droplet recovery is typically < 5 minutes on hardware failure.

---

## 1. Application Tier HA

### Phase 0 — Single VPS with Auto-Recovery

```
Supervisor (systemd)
  ├── schoolms-horizon     (restart=always, startretries=10)
  └── schoolms-scheduler   (restart=always)

PHP-FPM systemd
  ├── Restart=on-failure
  └── RestartSec=5s
```

If PHP-FPM crashes, systemd restarts it in <5 seconds. If Horizon crashes, Supervisor restarts it. This covers process-level failures without requiring a second server.

### Phase 1 — Two App Nodes + Load Balancer

```
Cloudflare / DO Load Balancer
         │
    ┌────┴────┐
  Node 1    Node 2
  (active)  (active)
    │           │
    └────┬──────┘
         │
   Shared state: Redis (sessions) + MySQL (data) + S3 (files)
```

**Session persistence:** Not needed — sessions are in Redis (shared between nodes). Any node can serve any request.

**Health check for LB:** `GET /health` → `{"status":"ok"}` required before LB routes traffic to node.

**Deployment:** Deploy to Node 2 first, verify health, then deploy to Node 1. Zero-downtime rolling deploy.

---

## 2. Database HA

### MySQL — DO Managed (Phase 0+)

DigitalOcean Managed MySQL provides:
- **Automated daily backups** with 7-day retention (point-in-time recovery)
- **Standby replica** in same region (promoted automatically on primary failure)
- **Failover time:** < 60 seconds (DNS cutover to standby IP)
- **Connection string:** Use the cluster hostname — it automatically points to the current primary

**Application behaviour during failover:** MySQL connection pool will see ~30–60s of connection errors. Laravel's retry logic (via `REDIS_MAX_RETRIES` pattern) is not applied to MySQL by default.

**Recommendation:** Add database connection retry to the app bootstrap:
```php
// config/database.php mysql options
'options' => [
    PDO::ATTR_TIMEOUT => 5,
],
```
And set `DB_TIMEOUT=5` env var. Requests that hit during failover return 500; health check will detect and LB will drain. Acceptable for 99.9% SLA.

### Read Replica for Reporting (Phase 1)

```php
// config/database.php
'mysql' => [
    'read' => ['host' => env('DB_READ_HOST', env('DB_HOST'))],
    'write' => ['host' => env('DB_HOST')],
    'sticky' => true,  // reads after writes go to write connection
    ...
]
```

Reporting-heavy queries (assessment summaries, fee reports) go to replica. Writes (student updates, fee records) go to primary. Replica lag < 100ms on DigitalOcean.

---

## 3. Cache & Session HA

### Redis — DO Managed with Eviction Policy

DO Managed Redis uses:
- `maxmemory-policy allkeys-lru` — evicts least-recently-used keys on memory pressure
- **Persistence:** AOF (append-only file) — survives Redis crash

**Impact of Redis unavailability:**
- Sessions lost → all users logged out (recoverable — just re-login)
- Cache miss → queries hit MySQL (performance degraded, not down)
- Queue unavailable → Horizon pauses job processing (jobs are not lost — Redis streams are durable)

**Phase 1 — Redis Sentinel:**
```
Sentinel 1  Sentinel 2
      │           │
  Redis Master ──► Redis Replica
```
Sentinel promotes replica to master in < 30 seconds on primary failure. PHP `phpredis` client handles Sentinel discovery natively via `REDIS_SENTINELS` env var.

---

## 4. Queue HA

### Horizon Resilience

Horizon workers are stateless — each job is processed independently. On worker crash:
1. Supervisor restarts the worker process immediately (< 1s)
2. The in-progress job is marked `failed` if the process died mid-execution
3. Failed jobs are visible in Horizon dashboard and can be retried

**Jobs with retry logic:**
- `GenerateReportCardJob`: `tries=3`, `backoff=[30, 60, 120]`
- `ExportSchoolDataJob`: `tries=2`
- Mail jobs: `tries=3`

**Notification queue redundancy:** `supervisor-notifications` has 6 workers. If 2 crash, 4 continue processing SMS/email. No downtime for notification delivery.

---

## 5. Storage HA

### S3 / DO Spaces

Object storage is inherently distributed — no single point of failure. DO Spaces provides 99.99% durability (4 nines) via replication across multiple storage nodes.

**For database backups specifically:**
```
Local: storage/app/backups/ (on VPS disk — single point)
Cloud: S3 private bucket (replicated — HA)
```

If VPS disk fails, cloud backup is recoverable. Backup command uploads to S3 on every run.

---

## 6. DNS & Network HA

### Cloudflare (Zero SPOF)

Cloudflare's anycast network has 300+ PoPs globally. DNS resolution and CDN caching are highly available by default.

**Wildcard DNS:** `*.schoolms.com.gh` → VPS IP. If VPS is down, DNS still resolves but connections fail. Cloudflare's "Always Online" feature serves cached pages during origin downtime.

**Health monitoring:** Set up Cloudflare Health Check → notify on origin failure → trigger alerting (PagerDuty or email).

---

## 7. Disaster Recovery

### RTO / RPO Targets

| Scenario | Recovery Time Objective (RTO) | Recovery Point Objective (RPO) |
|---|---|---|
| VPS process crash | < 30s (Supervisor restart) | 0 (no data loss) |
| VPS full crash | < 15 min (snapshot restore) | < 24h (daily backup) |
| DB primary failure | < 2 min (DO Managed failover) | < 1 min (synchronous replica) |
| Full region outage | < 4h (manual restore to new region) | < 24h |
| Accidental data deletion | < 30 min (point-in-time restore) | < 5 min (PITR) |

### Recovery Runbook

**VPS crash → restore from snapshot:**
```bash
# 1. Create new Droplet from latest DO Snapshot (DO dashboard)
# 2. Update DNS A record to new IP (or use floating IP — point floating IP)
# 3. Verify health check
curl https://schoolms.com.gh/health
# 4. Start Horizon and scheduler
systemctl start supervisor
```

**Data corruption → point-in-time restore:**
```bash
# DO Managed MySQL console → Restore → select timestamp before corruption
# New cluster created; update DB_HOST in .env
# php artisan migrate --force  (apply any missed migrations)
```

---

## 8. Uptime Monitoring

**Recommended tools (free tier sufficient for Phase 0):**

| Tool | Free Plan | Alert Method |
|---|---|---|
| UptimeRobot | 50 monitors, 5-min interval | Email, Slack, SMS |
| Betterstack | 10 monitors | Email, PagerDuty |
| Cloudflare Health Checks | Built-in | Cloudflare notifications |

**Monitors to configure:**
1. `https://schoolms.com.gh/health` — platform root
2. `https://superadmin.schoolms.com.gh/login` — super admin portal
3. `https://demo.schoolms.com.gh/` — a sample school website
4. SMTP: verify mail delivery monthly

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
