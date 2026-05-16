# SchoolMS Ghana — Infrastructure Recommendation

**Generated:** 2026-05-16  
**Target market:** Ghanaian K-12 schools (Basic schools primarily)  
**Constraint:** Cost-conscious; schools pay GHS 500–1 200/term

---

## Recommended Stack by Phase

---

## Phase 0 — Launch (1–50 schools)

### Primary Recommendation: DigitalOcean VPS + Managed Redis

| Component | Service | Spec | Monthly Cost |
|---|---|---|---|
| App server | DO Droplet | 4 vCPU / 8 GB RAM / 160 GB SSD | $48 |
| Database | DO Managed MySQL 8.0 | 1 vCPU / 1 GB RAM | $15 |
| Redis | DO Managed Redis 7 | 1 GB | $15 |
| Object storage | DO Spaces (S3-compatible) | 250 GB + CDN | $5 |
| Backups | DO Droplet Backups | Weekly snapshots | $10 |
| **Total** | | | **~$93/mo** |

**Why DigitalOcean:**
- Ghana data centre not available; Amsterdam (AMS3) is lowest-latency for West Africa (~120ms)
- Managed MySQL removes DBA burden for a small team
- DO Spaces is S3-compatible — Laravel's S3 disk works without code changes
- Simple UI, predictable pricing — no AWS billing surprises

**Alternative: Hetzner (Germany)**
- 4 vCPU / 8 GB: €6.50/mo (vs $48)
- No managed MySQL/Redis → higher ops burden
- Suitable if a sysadmin is available
- Total: ~$30/mo

### App Server Configuration

```
Ubuntu 24.04 LTS
PHP 8.4-FPM (php8.4-fpm, php8.4-mysql, php8.4-redis, php8.4-gd, php8.4-zip)
Nginx 1.27
Supervisor (Horizon + Scheduler)
Certbot (Let's Encrypt wildcard SSL for *.schoolms.com.gh)
```

### DNS Setup

```
schoolms.com.gh          A    <VPS IP>
*.schoolms.com.gh        A    <VPS IP>
*.admin.schoolms.com.gh  A    <VPS IP>
```

Wildcard SSL: `certbot certonly --dns-cloudflare -d "*.schoolms.com.gh" -d "*.admin.schoolms.com.gh"`

### Minimum Server Requirements

| Resource | Minimum | Recommended |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disk | 40 GB SSD | 160 GB SSD |
| PHP | 8.4 | 8.4 |
| MySQL | 8.0 | 8.0 |
| Redis | 7.0 | 7.2 |
| Node.js | 22 (build only) | 22 |

---

## Phase 1 — Growth (50–200 schools)

### Add to Phase 0

| Addition | Service | Cost |
|---|---|---|
| Read replica | DO Managed MySQL replica | +$15/mo |
| Redis Sentinel | 2 × $6 DO Droplets | +$12/mo |
| Second app node | DO Droplet 4 vCPU/8 GB | +$48/mo |
| Cloudflare Pro (WAF) | Cloudflare Pro | +$20/mo |
| **Phase 1 total** | | **~$188/mo** |

### Cloudflare Configuration

```
# Page Rules
schoolms.com.gh/build/*       Cache Level: Cache Everything, Edge TTL: 1 month
schoolms.com.gh/storage/*     Cache Level: Cache Everything, Edge TTL: 1 week
schoolms.com.gh/api/*         Cache Level: Bypass
*.admin.schoolms.com.gh/*     Cache Level: Bypass (admin should not be cached)
```

WAF rules: enable OWASP ruleset, Ghana IP allowlist for admin subdomain (optional).

---

## Phase 2 — Scale (200–500 schools)

### Kubernetes on DigitalOcean (DOKS)

| Component | Spec | Monthly Cost |
|---|---|---|
| DOKS cluster | 3 × 4 vCPU/8 GB nodes | $144 |
| DO Managed MySQL | 2 vCPU/4 GB + replica | $100 |
| DO Managed Redis | 2 GB cluster | $30 |
| DO Spaces + CDN | 1 TB | $20 |
| Cloudflare Pro | WAF + analytics | $20 |
| **Phase 2 total** | | **~$314/mo** |

### Kubernetes Manifests Required
- `Deployment/app` — PHP-FPM, 3 replicas, HPA CPU 70%
- `Deployment/horizon` — Queue workers, 2 replicas
- `CronJob/scheduler` — `php artisan schedule:run` every minute
- `Job/migrate` — runs `php artisan migrate --force` as init hook
- `ConfigMap` — non-secret env vars
- `Secret` — DB password, Redis password, APP_KEY
- `Service/nginx` — LoadBalancer type (DO provisions LB automatically)

---

## Network Architecture Diagram

```
Internet
   │
Cloudflare (WAF + CDN + DDoS protection)
   │
DigitalOcean Load Balancer (Phase 2+)
   │
   ├── Nginx (Phase 0: on VPS; Phase 2: in K8s)
   │      └── PHP-FPM (app container)
   │              ├── MySQL (managed)
   │              ├── Redis (managed)
   │              └── DO Spaces / S3 (object storage)
   │
   └── Horizon workers (dedicated container)
```

---

## Ghana-Specific Considerations

### Internet Reliability
- Ghana has frequent power outages → queue jobs must be idempotent (they are)
- MTN Ghana, AirtelTigo, Vodafone have variable latency to European servers (80–200ms)
- Recommend Cloudflare as CDN — Cloudflare has edge presence in Accra (GH-ACC) from 2023

### Mobile Data Costs
- Many parents use mobile data (expensive in Ghana)
- PWA implementation (already done) reduces repeat data usage via service worker caching
- Keep admin dashboard asset bundle < 500 KB gzipped (Vite code-splitting helps)
- Consider `data-saver` header detection to serve lower-res images

### SMS Gateway
- Hubtel is the recommended gateway (Ghanaian provider, all networks)
- Fallback: Arkesel, SMSOnlineGH
- SMS cost: ~GHS 0.04–0.06 per SMS (factor into subscription pricing)

### Payment Gateways
- Paystack Ghana: supports Visa/Mastercard + MTN Mobile Money + AirtelTigo Money + Vodafone Cash
- Moolre: Mobile Money focus (MTN MoMo primary)
- Recommend Paystack as default (`BILLING_GATEWAY=paystack`) — widest coverage

---

## Server Security Hardening (Post-Deploy)

```bash
# Firewall — allow only necessary ports
ufw allow 22/tcp    # SSH (consider non-standard port)
ufw allow 80/tcp    # HTTP (redirect to HTTPS)
ufw allow 443/tcp   # HTTPS
ufw deny 3306/tcp   # MySQL — DO Managed handles this; block anyway
ufw deny 6379/tcp   # Redis — same
ufw enable

# Fail2Ban — protect SSH and Nginx
apt install fail2ban
# Configure /etc/fail2ban/jail.local with nginx-http-auth, nginx-limit-req jails

# SSH hardening
# /etc/ssh/sshd_config:
# PasswordAuthentication no
# PermitRootLogin no
# PubkeyAuthentication yes
```

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
