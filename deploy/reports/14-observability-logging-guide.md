# SchoolMS Ghana — Observability & Logging Setup Guide

**Generated:** 2026-05-16

---

## Overview

SchoolMS Ghana implements a five-layer observability stack:

1. **Structured application logs** (StructuredLogger service)
2. **Business audit trail** (AuditObserver → `audit_logs` table)
3. **Error tracking** (Sentry)
4. **Queue monitoring** (Laravel Horizon)
5. **Uptime / health monitoring** (`/health` endpoint + UptimeRobot)

---

## 1. Structured Logger

### Service: `app/Services/StructuredLogger.php`

Every log entry is automatically enriched with:

```json
{
  "action": "student.created",
  "tenant_id": 42,
  "tenant": "accra-academy",
  "user_id": 7,
  "role": "school_admin",
  "ip": "102.89.23.1",
  "user_agent": "Mozilla/5.0...",
  "timestamp": "2026-05-16T06:30:00+00:00",
  // ... any extra context passed by caller
  "student_id": 1234,
  "class": "Primary 3A"
}
```

### Usage

```php
use App\Services\StructuredLogger;

class StudentController extends Controller
{
    public function __construct(private StructuredLogger $log) {}

    public function store(Request $request): RedirectResponse
    {
        $student = Student::create(...);

        $this->log->info('student.created', [
            'student_id' => $student->id,
            'class'      => $student->schoolClass->name,
        ]);
        // ...
    }
}
```

### Log Methods

| Method | Level | Use Case |
|---|---|---|
| `->info($action, $ctx)` | INFO | Normal business events |
| `->warning($action, $ctx)` | WARNING | Non-critical anomalies |
| `->error($action, $ctx)` | ERROR | Failures requiring attention |
| `->security($action, $ctx)` | WARNING `[SECURITY]` | Auth events, lockouts, suspicious activity |
| `->audit($action, $ctx)` | INFO `[AUDIT]` | Regulatory-level audit events |

### Security events automatically logged

- `login.failed` — failed login attempt (with email)
- `login.locked_out` — account locked out (too many attempts)
- (Add in controllers as needed: `login.success`, `2fa.failed`, `api.unauthorized`)

---

## 2. Business Audit Trail (AuditObserver)

### Table: `audit_logs`

Every `create`, `update`, `delete` on observed models writes a row:

| Column | Content |
|---|---|
| `tenant_id` | Which school |
| `user_id` | Who performed the action (nullable for system actions) |
| `model_type` | Eloquent model class |
| `model_id` | Record ID |
| `action` | `created` \| `updated` \| `deleted` |
| `old_values` | JSON of changed columns before |
| `new_values` | JSON of changed columns after |
| `ip_address` | Client IP |
| `created_at` | Exact timestamp |

### Observed Models

Student, Teacher, Fee, Assessment, Admission, SchoolClass, Subject, FeedingFee

### Retention

Pruned annually by `php artisan audit:prune --days=365` (scheduled monthly).

### Admin Audit Log Viewer

School admins can view their tenant's audit log at `GET /audit` (if `AuditController` is routed in `admin.php`). Automatically scoped to `tenant_id` — no cross-tenant leakage possible.

---

## 3. Error Tracking (Sentry)

### Configuration

```bash
# .env
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/12345
SENTRY_TRACES_SAMPLE_RATE=0.1   # 10% of requests traced
SENTRY_PROFILES_SAMPLE_RATE=0.1 # 10% profiled (requires Performance add-on)
```

### What Sentry captures

- Unhandled exceptions (auto-captured by `config/sentry.php`)
- Performance traces (slow queries, N+1 detection)
- Queue job failures (Horizon error handler integration)

### Recommended alerts in Sentry

| Alert | Threshold | Channel |
|---|---|---|
| Error rate spike | >10 errors/5min | Email + Slack |
| New issue type | First occurrence | Email |
| P95 response time | >2 seconds | Slack |
| Backup failure | Any `CRITICAL` log | PagerDuty |

---

## 4. Queue Monitoring (Laravel Horizon)

### Dashboard Access

- **URL:** `/horizon`
- **Production auth:** `role === 'super_admin'` only
- **Local:** Open to all authenticated users

### What Horizon shows

- Queue depths (all 5 queues: default, pdf, exports, notifications, sms)
- Job throughput (jobs/min per supervisor)
- Failed jobs with full stack traces
- Worker memory usage (alerts at 128 MB threshold)
- Wait times per queue

### Horizon Alerts

Set in `config/horizon.php`:
```php
'environments' => [
    'production' => [
        'supervisor-default' => [
            'maxTime'   => 90,    // job timeout
            'maxTries'  => 3,     // retry attempts
        ],
    ],
],
```

Failed jobs remain in Horizon for 7 days (Redis). Can be retried via dashboard or:
```bash
php artisan horizon:retry all
php artisan horizon:retry {jobId}
```

---

## 5. Health Check Endpoint

### `GET /health`

Returns:
```json
{
  "status": "ok",
  "checks": {
    "database": {"status": "ok"},
    "cache": {"status": "ok"},
    "queue": {"status": "ok", "sizes": {"default": 0, "pdf": 0, "exports": 0, "notifications": 0, "sms": 0}},
    "storage": {"status": "ok"},
    "horizon": {"status": "ok", "heartbeat_age_seconds": 4},
    "saas_metrics": {"status": "warn", "active_tenants": 12, "active_subscriptions": 9}
  }
}
```

- `200 OK` when all critical checks pass
- `503 Degraded` when any critical check fails

**Critical checks:** database, cache, queue, storage  
**Non-critical (warn only):** horizon, saas_metrics

### Use in monitoring

```bash
# UptimeRobot: HTTP keyword monitor
# URL: https://schoolms.com.gh/health
# Keyword to find: "status":"ok"
# Alert if missing: email + SMS

# Kubernetes liveness probe
livenessProbe:
  httpGet:
    path: /health
    port: 80
  initialDelaySeconds: 30
  periodSeconds: 10
```

---

## 6. Log Channels (config/logging.php)

```php
// Production recommended: stack = daily + stderr
'stack' => [
    'driver'   => 'stack',
    'channels' => ['daily', 'stderr'],
],
'daily' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/laravel.log'),
    'days'   => 30,
    'level'  => env('LOG_LEVEL', 'error'),  // 'debug' locally
],
```

**Structured JSON logging (production):**
Add `'formatter' => Monolog\Formatter\JsonFormatter::class` to the daily channel config. This outputs one JSON object per line, parseable by Loki, CloudWatch, or Datadog.

---

## 7. Performance Monitoring

### Slow Query Detection

MySQL slow query log enabled in `docker/mysql/my.cnf`:
```ini
slow_query_log        = 1
slow_query_log_file   = /var/log/mysql/slow.log
long_query_time       = 1
```

Review monthly: `mysqldumpslow -s t /var/log/mysql/slow.log | head -20`

### N+1 Detection (Development)

Laravel's strict mode catches lazy loading in non-production:
```php
Model::preventLazyLoading(! app()->isProduction());
```

Any lazy-loaded relationship throws `LazyLoadingViolationException` in local/testing, forcing eager loading to be added before reaching production.

---

*Report generated by SchoolMS Ghana Enterprise Audit — 2026-05-16*
