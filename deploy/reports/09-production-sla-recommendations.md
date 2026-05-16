# SchoolMS Ghana — Production SLA Recommendations

**Generated:** 2026-05-16  
**Audience:** Platform operators, SaaS agreement drafters, school IT contacts

---

## 1. Recommended SLA Tiers

| Tier | Target Uptime | Max Monthly Downtime | Eligible Plans |
|---|---|---|---|
| Standard | 99.5% | 3.6 h | Basic |
| Professional | 99.9% | 43.8 min | Standard |
| Enterprise | 99.95% | 21.9 min | Premium |

**Phase 0 realistic commitment:** 99.5% (Standard) — single VPS, no redundancy.  
**Phase 1 realistic commitment:** 99.9% (Professional) — dual nodes, managed DB.  
**Phase 2 realistic commitment:** 99.95% (Enterprise) — Kubernetes, multi-AZ.

---

## 2. SLA Exclusions (Standard Clauses)

The following do not count against uptime:

1. **Scheduled maintenance windows** — communicated ≥ 48 hours in advance via email + in-app banner. Max 4 hours/month.
2. **Force majeure** — power grid failure, ISP outage, DDoS attacks beyond Cloudflare mitigation capacity.
3. **Third-party service outages** — Paystack, Moolre, Hubtel SMS, AWS S3 outages.
4. **Customer-caused outages** — incorrect DNS configuration, revoked API credentials.
5. **Free trial tenants** — SLA does not apply during trial period.

---

## 3. Service Level Indicators (SLIs)

These are the metrics that determine SLA compliance:

### 3.1 — Availability SLI

```
Availability = (Total minutes in period - Downtime minutes) / Total minutes × 100
```

**Downtime** is defined as: `/health` endpoint returning non-200 or timing out for ≥ 3 consecutive checks (15 minutes at 5-min poll interval).

**Measurement tool:** UptimeRobot or Betterstack with 1-minute polling.

### 3.2 — Latency SLI

| Metric | Target |
|---|---|
| Admin dashboard p50 | < 200ms |
| Admin dashboard p95 | < 500ms |
| Parent portal lookup p95 | < 300ms |
| API response p95 | < 200ms |
| Report card PDF generation | < 30s (async — job completion) |

Latency is measured from Cloudflare edge to origin (excludes last-mile user connection).

### 3.3 — Queue Processing SLI

| Queue | Target processing time (p95) |
|---|---|
| SMS / notifications | < 30s from dispatch |
| Email | < 2 min from dispatch |
| PDF report cards | < 10 min from dispatch |
| Data exports | < 30 min from dispatch |

### 3.4 — Data Durability SLI

Target: **99.999%** — no data loss. Achieved via:
- MySQL synchronous replica (DO Managed)
- Daily encrypted backups retained 7 days
- S3 cloud backup with 30-day retention

---

## 4. Incident Response Procedures

### Severity Classification

| Severity | Definition | Response Time | Resolution Target |
|---|---|---|---|
| P1 — Critical | All tenants cannot access platform | 15 min | 1 hour |
| P2 — Major | > 20% of tenants affected or payment processing down | 30 min | 4 hours |
| P3 — Minor | Single tenant affected or degraded performance | 2 hours | 24 hours |
| P4 — Informational | Non-critical feature degraded | Next business day | 1 week |

### P1 Response Runbook

```
1. Alert fires (UptimeRobot → PagerDuty → on-call engineer)
2. Engineer verifies: curl https://schoolms.com.gh/health
3. Check server SSH access: ssh deploy@<vps-ip>
4. Check PHP-FPM: systemctl status php8.4-fpm
5. Check Nginx: systemctl status nginx
6. Check MySQL: php artisan db:monitor
7. Check Redis: redis-cli -a $REDIS_PASSWORD ping
8. If process down → restart: systemctl restart <service>
9. If VPS unreachable → DO dashboard → Power cycle → if no response → Restore from snapshot
10. Post-incident: write incident report within 24 hours
```

### Communication Template (P1/P2)

```
Subject: [SchoolMS] Service Disruption — [DATE] [TIME] WAT

We are aware of a disruption affecting the SchoolMS platform.
Affected: [All tenants / Specific feature]
Start time: [TIME] WAT
Current status: Investigating / Identified / Resolving

We will provide an update within 30 minutes.
```

Post-resolution:
```
Subject: [SchoolMS] Service Restored — [DATE]

The issue has been resolved as of [TIME] WAT.
Duration: [X] minutes
Root cause: [Brief explanation]
Preventive measures: [Actions taken]
```

---

## 5. Maintenance Windows

### Recommended Schedule

| Window | Time (WAT) | Frequency | Purpose |
|---|---|---|---|
| Minor updates | Saturdays 22:00–24:00 | Weekly | Config changes, minor deploys |
| Major migrations | Sundays 01:00–04:00 | Monthly | Schema migrations, version upgrades |
| Security patches | As needed | Emergency | CVE patches (no window required) |

**School calendar awareness:**
- Avoid maintenance during BECE week (typically June/July)
- Avoid term-end weeks (highest report card generation load)
- Coordinate with Ghana Education Service academic calendar

### In-App Maintenance Banner

Use the maintenance mode built into Laravel:
```bash
php artisan down --message="Scheduled maintenance — back at 04:00 WAT" --retry=300
# ... perform maintenance ...
php artisan up
```

The `503 Maintenance` page should include:
- Expected completion time
- Emergency contact (WhatsApp number for school admins)
- Status page URL

---

## 6. Backup & Data Retention Policy

| Data Type | Retention | Storage |
|---|---|---|
| Database backups | 7 days local, 30 days cloud | VPS + S3 private |
| Audit logs | 12 months rolling | MySQL (pruned monthly) |
| Student records | Indefinite (soft delete) | MySQL |
| Exported ZIP files | 24 hours | VPS local |
| Application logs | 30 days | Laravel log + Sentry |
| Horizon job history | 7 days | Redis |

### GDPR / Data Privacy Compliance

Ghana does not yet have a fully enacted GDPR-equivalent (Data Protection Act 2012 is in force). Recommended practices:
- Do not store student PII beyond what is required for school operations
- Provide school admins with a data export on request
- Honour deletion requests: `Student::withTrashed()->find($id)->forceDelete()` removes all related records via cascades
- Document data flows in a Privacy Policy visible at `{slug}.schoolms.com.gh/privacy`

---

## 7. Performance Baselines & Alerts

### Alert Thresholds

| Metric | Warning | Critical | Action |
|---|---|---|---|
| CPU usage (5-min avg) | 70% | 90% | Scale or investigate |
| Memory usage | 80% | 95% | Restart PHP-FPM or scale |
| MySQL connections | 200/300 | 280/300 | Add ProxySQL |
| Redis memory | 70% | 90% | Increase instance or evict |
| Disk usage | 75% | 90% | Expand disk or clean exports |
| Queue depth (default) | 500 jobs | 2 000 jobs | Scale Horizon workers |
| Failed job rate | 5/hour | 20/hour | Investigate + alert |

### Monitoring Setup

```bash
# Install netdata for real-time monitoring (free, self-hosted)
wget -O /tmp/netdata-kickstart.sh https://get.netdata.cloud/kickstart.sh
sh /tmp/netdata-kickstart.sh --non-interactive

# Access at http://<vps-ip>:19999 (firewall-restrict to VPN/admin IP)
```

---

## 8. Support SLA

| Plan | Support Channel | Response Time |
|---|---|---|
| Basic | Email only | 2 business days |
| Standard | Email + WhatsApp | 4 business hours |
| Premium | Email + WhatsApp + Phone | 1 business hour |

Business hours: Monday–Friday 08:00–17:00 WAT.

**Escalation path:**
1. First response: Customer Success (email/WhatsApp)
2. Technical issue: Platform Engineer
3. Critical incident: CTO / Lead Developer

---

## 9. Production Readiness Score

| Category | Score | Notes |
|---|---|---|
| Security | 9/10 | SecureHeaders, HMAC webhooks, 2FA, rate limiting. -1 for permissive CSP |
| Multi-tenant isolation | 10/10 | HasTenantScope, middleware, job rebinding — fully audited |
| Availability | 7/10 | Single VPS SPOF at Phase 0. Rises to 9/10 at Phase 1 |
| Observability | 7/10 | Health check, Sentry, Horizon, audit log. Missing Prometheus/Grafana |
| Deployment | 8/10 | CI/CD, Docker, atomic deploy pattern. Missing K8s manifests |
| Performance | 8/10 | OPcache, indexes, Redis cache, CDN-ready. Missing read replica |
| Data durability | 9/10 | Daily backups, S3 cloud, soft deletes, audit trail |
| Scalability | 7/10 | Architecture is horizontally scalable; infra not yet scaled |
| Documentation | 9/10 | CLAUDE.md, .env.example, PRODUCTION.md, OpenAPI spec |
| Test coverage | 9/10 | 704 tests, 1 431 assertions, all green |

**Overall: 8.3 / 10**

The platform is production-ready at Phase 0 scale with the understanding that the single-VPS SPOF is acceptable for an early-stage product. The architecture correctly anticipates Phase 1 and 2 scaling without requiring rewrites.

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
