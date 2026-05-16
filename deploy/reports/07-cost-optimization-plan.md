# SchoolMS Ghana — Cost Optimization Plan

**Generated:** 2026-05-16  
**Goal:** Minimize infrastructure cost while maintaining performance and reliability

---

## Revenue vs Cost Context

| Plan | Price (GHS/term) | Price (USD/term, ~$1=GHS13) | Break-even tenants at Phase 0 |
|---|---|---|---|
| Basic | GHS 500 | ~$38 | 3 schools cover $93/mo infra |
| Standard | GHS 850 | ~$65 | 2 schools cover $93/mo |
| Premium | GHS 1 200 | ~$92 | 1.5 schools cover $93/mo |

**At 10 paying schools (mixed plans) → ~$500/term → infra cost is ~6% of revenue.**  
Margins are healthy from launch. Cost optimization becomes critical at Phase 2 ($300+/mo).

---

## 1. Immediate Savings (Phase 0)

### 1.1 — Use Hetzner Instead of DigitalOcean (Save $30–40/mo)

| Item | DO | Hetzner | Saving |
|---|---|---|---|
| 4 vCPU / 8 GB VPS | $48 | €6.50 (~$7) | -$41/mo |
| Managed MySQL | $15 | Self-managed | -$15 but +ops burden |
| Managed Redis | $15 | Self-managed | -$15 but +ops burden |

**Recommendation:** Use Hetzner VPS with self-managed MySQL/Redis only if a developer is available to maintain it. For a small team, DO Managed saves hours of DBA work worth more than $30.

**Hybrid:** Hetzner VPS ($7) + DO Managed MySQL ($15) + DO Managed Redis ($15) = $37/mo. Saves $56/mo vs full DO.

### 1.2 — Backblaze B2 Instead of S3/DO Spaces (Save $3–5/mo)

- Backblaze B2: $0.006/GB storage, $0.01/GB egress (10× cheaper than S3)
- Laravel's S3 disk works with B2 via custom endpoint: `AWS_ENDPOINT=https://s3.us-west-004.backblazeb2.com`
- Use Cloudflare as CDN in front of B2 → **egress is free** (Cloudflare + B2 bandwidth alliance)
- **Save:** ~$5/mo on object storage for 250 GB

### 1.3 — Disable Sentry in Dev/Staging (Save $0 but avoid quota burn)

- Sentry free tier: 5 000 errors/month
- Set `SENTRY_TRACES_SAMPLE_RATE=0` and `SENTRY_PROFILES_SAMPLE_RATE=0` on staging
- Only enable traces/profiles in production

### 1.4 — Let's Encrypt Wildcard SSL (Free vs Paid SSL)

- Already using Let's Encrypt in `deploy/PRODUCTION.md`
- Saves $50–200/year vs commercial wildcard SSL
- Renews automatically via certbot systemd timer

---

## 2. Compute Optimization

### 2.1 — Right-Size PHP-FPM Workers

Current `php-fpm.conf` uses dynamic process management. Optimal worker count:

```
pm.max_children = (Available RAM - OS/Redis/MySQL overhead) / per-worker RAM
                = (8 192 MB - 2 048 MB) / 32 MB
                ≈ 190 (cap at 50 to avoid MySQL connection exhaustion)
```

**Recommendation:**
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500  ; recycle workers to prevent memory leaks
```

Over-provisioning workers wastes RAM. Under-provisioning causes queue buildup at peak.

### 2.2 — OPcache Tuning

Already set in `docker/php/php.ini`:
```ini
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  ; NEVER check disk in production
```

`validate_timestamps=0` eliminates one `stat()` call per file per request — critical for 200+ file Laravel bootstrap. **Impact:** 15–25% throughput improvement on cache-warm requests.

### 2.3 — Horizon Worker Right-Sizing

Current config (Phase 0 `local`): 2 default + 1 pdf + 1 exports + 2 notifications = 6 workers.  
Each worker: ~64 MB RAM. Total: ~384 MB for Horizon.

**Peak load calculation:**
- 50 schools × 2 concurrent report card jobs = 100 PDF jobs
- At 1 job/180s per worker, 4 PDF workers = 720 jobs/hour capacity
- That's sufficient for a full school term-end cycle

**At 200+ schools:** Scale pdf workers to 8, notifications to 12. Add supervisor auto-scaling rule.

---

## 3. Database Optimization

### 3.1 — Connection Pooling (PgBouncer / ProxySQL)

PHP-FPM workers open a new MySQL connection per request (PHP is stateless). At 50 workers × 5 apps = 250 concurrent connections. MySQL's `max_connections=151` default is insufficient.

**Solutions:**
- **Phase 0:** Set `max_connections=300` in `docker/mysql/my.cnf` (already done: `max_connections=300`)
- **Phase 1:** Add ProxySQL in front of MySQL; pools 250 PHP connections into 50 MySQL connections. Reduces MySQL RAM by ~30%

### 3.2 — Slow Query Elimination

Slow query log enabled in `docker/mysql/my.cnf` (`slow_query_log=1`, `long_query_time=1`).

**Current indexes added (`2026_05_14_100000_add_performance_indexes.php`):**
```
students(tenant_id, status)
students(tenant_id, school_class_id, status)
fees(tenant_id, student_id, status)
assessments(tenant_id, student_id, term_id)
```

**Review slow query log after 30 days of production data.** Common missing indexes:
- `audit_logs(tenant_id, created_at)` — for pruning performance (monthly job)
- `sessions(user_id)` — if sessions table grows large (mitigated: Redis sessions)

### 3.3 — Audit Log Archival

`PruneAuditLogsCommand` runs monthly, deletes logs older than 365 days in 1 000-row batches. This prevents the `audit_logs` table from growing unboundedly and slowing multi-tenant queries.

**Storage saving:** Typical school generates ~500 audit events/month. At 50 schools × 12 months = 300 000 rows/year. Pruning after 12 months caps the table at ~300 000 rows (~150 MB) — manageable.

---

## 4. Bandwidth & CDN Cost

### 4.1 — Serve Assets from CDN

With Cloudflare free tier:
- `/build/*` assets: Cloudflare caches indefinitely (immutable hash in filename)
- School website pages: 5-min cache (most content is static)
- Admin portal: bypass cache (dynamic, auth-required)

**Bandwidth saving:** 70–80% of VPS egress eliminated. At $0.01/GB on DO, saves $10–30/mo at moderate traffic.

### 4.2 — Image Optimization

School logos are uploaded as JPEG/PNG (up to 2 MB). Before storing:
1. Resize to max 256×256 px (already sufficient for nav use)
2. Convert to WebP (50% smaller than JPEG at same quality)

**Not yet implemented.** Add `Intervention\Image` processing in `UpdateSchoolProfileRequest` handler.

### 4.3 — PWA Cache Reduces Repeat Bandwidth

Service worker caches `/build/*` assets on first visit. Returning users load zero JS/CSS from server. Especially valuable in Ghana where mobile data is expensive.

---

## 5. Cost Projection

| Phase | Schools | Infra Cost/mo | Revenue/mo (avg $55/school/term ÷ 3) | Margin |
|---|---|---|---|---|
| 0 | 10 | $93 | $183 | 49% |
| 0 | 30 | $93 | $550 | 83% |
| 1 | 100 | $188 | $1 833 | 90% |
| 2 | 300 | $314 | $5 500 | 94% |
| 2 | 500 | $400 | $9 167 | 96% |

Infra cost as % of revenue shrinks rapidly — the bottleneck becomes sales/support, not servers.

---

## 6. Optimization Priority Order

| Priority | Action | Saving | Effort |
|---|---|---|---|
| 1 | Backblaze B2 + Cloudflare (free egress) | $5/mo | 1 hour |
| 2 | OPcache validate_timestamps=0 | 20% CPU | Already done |
| 3 | Right-size FPM workers (50 max_children) | RAM | 5 min |
| 4 | ProxySQL (Phase 1) | DB connections | 2 hours |
| 5 | WebP image conversion on upload | Bandwidth | 2 hours |
| 6 | Hetzner hybrid (if ops capacity) | $41/mo | 1 day |

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
