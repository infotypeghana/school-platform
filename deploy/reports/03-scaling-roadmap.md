# SchoolMS Ghana — Scaling Roadmap

**Generated:** 2026-05-16  
**Horizon:** 18 months (3 phases)

---

## Current Baseline (Phase 0 — Live)

| Metric | Current Capacity |
|---|---|
| Tenants | 1–50 schools |
| Concurrent users | ~200 (single VPS) |
| Database | Single MySQL 8.0 on VPS |
| Queue workers | Horizon (4 supervisors, 20 workers) |
| Cache/Session | Redis 7 (single node, 3 DBs) |
| Storage | Local disk + S3 backup |
| Deployment | Single VPS / Docker Compose |

**Bottleneck at this tier:** MySQL (single writer) and PHP-FPM worker count.

---

## Phase 1 — Growth (50–200 schools, 0–6 months)

### Target
- 200 concurrent admin users
- 2 000 daily parent portal sessions
- Sub-300ms p95 response time

### Changes Required

#### 1.1 — Database Read Replica
```
Master (writes)  ──┬──  Replica 1 (reads)
                   └──  (future Replica 2)
```
- Add `DB_READ_HOST` env var; configure Laravel `read/write` connection split in `config/database.php`
- Route reporting queries (assessments, fees, report cards) to read connection
- **Cost:** ~$20/mo extra on DigitalOcean (1 GB replica)
- **Impact:** Removes read load from write master; query latency drops ~40%

#### 1.2 — Redis Sentinel (HA)
- Replace single Redis with 1 master + 2 sentinels
- Zero-downtime failover on Redis crash
- **Cost:** 2 extra $6/mo droplets
- **Config change:** `REDIS_CLIENT=predis`, add `options.cluster=sentinel` in `config/database.php`

#### 1.3 — Horizontal PHP-FPM (2 app nodes)
- Add second VPS, shared NFS or S3 for `storage/app`
- Nginx upstream load balances between both nodes
- **Requirement:** Sticky sessions NOT needed — sessions in Redis already shared
- **Cost:** ~$20/mo extra

#### 1.4 — CDN for Static Assets
- CloudFlare free tier in front of Nginx
- Cache `/build/*` (immutable), public school website pages (5-min TTL)
- `APP_URL` and `ASSET_URL` updated to CDN hostname
- **Cost:** Free (Cloudflare free tier)
- **Impact:** 60–80% reduction in static asset bandwidth from VPS

#### 1.5 — Queue Scaling
- Increase Horizon `supervisor-notifications` to 10 max processes (from 6)
- Add dedicated SMS retry queue with 3 attempts and 60s backoff

### Phase 1 Deliverables
- [ ] MySQL read replica configured
- [ ] Redis Sentinel deployed
- [ ] Second app node + Nginx upstream
- [ ] Cloudflare DNS proxy enabled
- [ ] S3 `MEDIA_DISK` + `PRIVATE_DISK` for shared storage

---

## Phase 2 — Scale (200–500 schools, 6–12 months)

### Target
- 1 000 concurrent admin users
- 10 000 daily parent sessions
- Sub-200ms p95

### Changes Required

#### 2.1 — Managed Database (PlanetScale or AWS RDS)
- Migrate from self-managed MySQL to managed RDS Multi-AZ
- Automated backups, point-in-time recovery, automated failover
- Enable ProxySQL connection pooling (PHP-FPM opens many short-lived connections)
- **Cost:** ~$100–$200/mo (RDS db.t3.medium Multi-AZ)

#### 2.2 — Kubernetes (EKS / DigitalOcean DOKS)
```
Ingress (Nginx) → app Deployment (3–10 pods, HPA on CPU)
                → horizon Deployment (2 pods)
                → scheduler CronJob (1 pod, every minute)
```
- HPA auto-scales `app` pods on 70% CPU
- `php artisan schoolms:install` baked into init container
- Health check (`/health`) wired to K8s liveness + readiness probes

#### 2.3 — Meilisearch for Student/Teacher Search
- Replace `LIKE '%...%'` queries with indexed full-text search
- Laravel Scout + Meilisearch driver
- Index: `students` (name, admission_number, tenant_id), `teachers` (name, staff_id)
- **Cost:** Meilisearch Cloud ~$30/mo or self-hosted

#### 2.4 — Separate Tenant Schemas (Optional — evaluate at 300+ tenants)
- Migrate from shared-schema to per-tenant MySQL schema
- Benefit: simpler backup isolation, schema-level DROP for churn
- Cost: migration complexity is very high; evaluate only if regulatory requirements demand it
- **Recommendation:** Defer unless GDPR/data residency is required

#### 2.5 — Multi-Region Consideration
- Primary: eu-west-1 (London, lowest latency from Ghana)
- Read replica: af-south-1 (Cape Town, ~30ms from Accra vs ~80ms London)
- Sessions/cache remain in primary region

### Phase 2 Deliverables
- [ ] Managed RDS + ProxySQL
- [ ] Kubernetes cluster + HPA
- [ ] Meilisearch search service
- [ ] Multi-region read replica in af-south-1
- [ ] CI/CD pipeline deploying to K8s (replace SSH deploy)

---

## Phase 3 — Enterprise (500+ schools, 12–18 months)

### Target
- 5 000+ concurrent users
- 99.99% uptime SLA
- Multi-region active-active (West Africa)

### Changes Required

#### 3.1 — Global Load Balancing
- AWS Global Accelerator or Cloudflare Load Balancing
- Route Ghana users to Lagos edge, other West Africa to London
- Latency-based routing with health failover

#### 3.2 — Event-Driven Architecture
- Replace synchronous subscription lifecycle with event bus (AWS EventBridge or Laravel Event Sourcing)
- Benefits: audit trail, replay, decoupled billing service
- Decouple SMS/notification service into standalone microservice

#### 3.3 — Tenant Data Portability (GDPR/Compliance)
- `php artisan tenant:export {slug}` — full tenant data dump in JSON
- `php artisan tenant:delete {slug}` — GDPR right-to-erasure
- Per-tenant encryption key rotation

#### 3.4 — Observability Stack
- Prometheus + Grafana for metrics (replace manual health check)
- OpenTelemetry traces from PHP → Jaeger
- Structured JSON logs → Loki → Grafana
- PagerDuty integration for alert routing

### Phase 3 Deliverables
- [ ] Global Accelerator / Cloudflare LB
- [ ] Event bus for billing lifecycle
- [ ] Tenant export/erasure commands
- [ ] Full observability stack (Prometheus, Grafana, Loki)

---

## Scaling Cost Summary

| Phase | Schools | Monthly Infra Cost (est.) | Key Investment |
|---|---|---|---|
| 0 (current) | 1–50 | $40–$80 | Single VPS + S3 |
| 1 | 50–200 | $120–$200 | Read replica, Redis HA, CDN |
| 2 | 200–500 | $400–$800 | Managed DB, Kubernetes |
| 3 | 500+ | $1 500–$4 000 | Multi-region, event bus |

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
