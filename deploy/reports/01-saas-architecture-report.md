# SchoolMS Ghana — SaaS Architecture Report

**Generated:** 2026-05-16  
**Version:** 1.0  
**Platform:** Laravel 13.8 / PHP 8.4 / MySQL 8.0 / Redis 7

---

## Executive Summary

SchoolMS Ghana is a multi-tenant SaaS platform using a **shared-database, schema-per-tenant-id** isolation model. All school data lives in a single MySQL schema with a `tenant_id` foreign key enforced by an Eloquent global scope. Routing uses subdomain-based tenant resolution. The architecture supports horizontal scaling of the PHP application tier while keeping the database layer vertically scaled (with read replicas planned for Phase 2).

**Current production readiness score: 8.6 / 10**

---

## 1. Tenant Architecture

### Resolution Strategy

```
Request → Nginx → PHP-FPM → ResolveTenantMiddleware
                                 │
                  ┌──────────────┴──────────────────┐
                  ▼                                   ▼
         Slug match                          Custom domain match
   {slug}.schoolms.com.gh           tenant.domain = request host
                  │                                   │
                  └──────────────┬──────────────────┘
                                 ▼
                    app()->instance('currentTenant', $tenant)
                    HasTenantScope auto-applies WHERE tenant_id = ?
```

**Subdomain groups (routes/web.php):**

| Subdomain Pattern | Purpose | Auth |
|---|---|---|
| `superadmin.schoolms.com.gh` | Platform super admin | `super.admin` middleware |
| `{slug}.admin.schoolms.com.gh` | School admin portal | `school.admin` + `subscription.admin` |
| `{slug}.schoolms.com.gh` | Public school website | None (grace/lock aware) |

### Data Isolation

All school-data models carry `HasTenantScope`:

```
Student, Teacher, SchoolClass, Subject, Attendance,
Assessment, ReportCard, Fee, Admission
```

Global scope applied at boot: `Model::addGlobalScope(new TenantScope)`. Bypass with `Model::withoutTenantScope()` (super admin only).

**Models intentionally without scope (shared/platform data):**
`Tenant, AcademicYear, AcademicTerm, Subscription, Payment, SubscriptionNotification`

### Tenant Lifecycle

```
onboarding → trial → active ──────────────────┐
                               term end + 5d   │
                          grace ───────────────┤
                              6th day unpaid   │
                          locked ──────────────┤
                          admin action         │
                          suspended ───────────┘
```

Daily lifecycle job (`CheckSubscriptionStatusJob`) runs at 06:00 WAT.

---

## 2. Authentication Architecture

### Three Auth Layers

| Layer | Mechanism | Session Key |
|---|---|---|
| School Admin | Laravel Auth (users table, role=school_admin) | Standard Auth |
| Teacher Portal | Custom session auth | `teacher_portal_id` |
| Parent Portal | Admission number + DOB lookup | `parent_portal_student_id` |
| Super Admin | Laravel Auth (role=super_admin) | Standard Auth |
| Mobile API | Sanctum token (30-day expiry) | Bearer token |

### 2FA (TOTP)
- Package: `pragmarx/google2fa-laravel`
- RFC 6238 TOTP with ±60s tolerance window
- Middleware `EnsureTwoFactorVerified` guards admin + superadmin routes
- Opt-in per user; disable requires current password confirmation

---

## 3. Queue Architecture

**Four Horizon supervisors:**

| Supervisor | Queues | Prod Workers | Timeout | Purpose |
|---|---|---|---|---|
| supervisor-default | `default` | 8 | 90s | Misc, emails, lifecycle jobs |
| supervisor-pdf | `pdf` | 4 | 180s | `GenerateReportCardJob` |
| supervisor-exports | `exports` | 2 | 300s | `ExportSchoolDataJob` |
| supervisor-notifications | `notifications, sms` | 6 | 30s | SMS, push, real-time alerts |

Redis DB 0 is exclusively used for queue storage (isolated from cache DB 1 and session DB 2).

---

## 4. Caching Architecture

| Cache Layer | Redis DB | TTL | Content |
|---|---|---|---|
| Application cache | DB 1 | Varies | Subscription status (5 min), computed data |
| Session | DB 2 | 120 min | Admin/teacher/parent session state |
| Horizon metrics | DB 0 | 4h | Queue snapshots |

**Cache-aside pattern** used for subscription status checks (hot path on every request for subscribed features). `BILLING_CACHE_TTL=5` minutes — tunable per deployment.

---

## 5. Storage Architecture

| Disk | Dev | Production |
|---|---|---|
| `MEDIA_DISK` | `public` (local symlink) | `s3` (public bucket, CDN optional) |
| `PRIVATE_DISK` | `local` | `s3` (private bucket) |
| Exports | `storage/app/exports/` | Same + auto-expiry at 24h |
| Backups | `storage/app/backups/` | + S3 cloud copy (30-day rotation) |

---

## 6. API Architecture

**REST API v1 (`/api/v1/`):**
- Auth: Sanctum bearer token, 30-day expiry
- Tenant resolution: `SetTenantFromToken` middleware reads `user.tenant_id`
- OpenAPI 3.1 spec at `public/api/openapi.yaml`, Swagger UI at `/api-docs`
- Route model binding intentionally disabled for tenant-scoped models (would bypass `HasTenantScope`)

---

## 7. Payment Architecture

**Dual gateway with abstraction:**

```
BillingController
     │
     ├── PaystackGateway (Ghana cards + Mobile Money)
     │       └── Webhook: HMAC-SHA512 (X-Paystack-Signature)
     │           + replay-attack prevention (5-min window + Redis idempotency)
     │
     └── MoolreGateway (Mobile Money focus)
             └── Webhook: HMAC-SHA256 (X-Moolre-Signature)
```

Active gateway selectable per tenant via `BILLING_GATEWAY` env var.

---

## 8. Monitoring Architecture

| Tool | Endpoint | Purpose |
|---|---|---|
| Health check | `GET /health` | LB/K8s liveness probe |
| Laravel Horizon | `/horizon` | Queue worker monitoring |
| Sentry | SENTRY_LARAVEL_DSN | Error tracking (10% traces/profiles) |
| Audit log | `audit_logs` table | CRUD trail per tenant |

Health check checks: database, Redis cache, queue depth, storage write, Horizon heartbeat, SaaS metrics (non-critical).

---

## 9. Architecture Gaps & Recommendations

| Gap | Priority | Effort |
|---|---|---|
| Read replica for reporting queries | High | Medium |
| CDN for static assets (Cloudflare) | High | Low |
| Dedicated search service (Meilisearch) | Medium | High |
| Event sourcing for billing history | Medium | High |
| GraphQL for mobile API v2 | Low | High |
| Tenant data export (GDPR) | Medium | Medium |

---

*Report generated by SchoolMS Ghana Platform Audit — 2026-05-16*
