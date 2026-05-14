# SchoolMS Ghana — AI Context Document

## Project Overview
Multi-tenant SaaS School Management Platform for Ghanaian basic schools. Built with **Laravel 13.8 / PHP 8.4**.

## Architecture

### Multi-Tenancy
- **Strategy**: Shared database with `tenant_id` column isolation
- **Tenant resolution**: Subdomain-based via `ResolveTenantMiddleware`
  - `superadmin.schoolms.com.gh` → super admin portal
  - `{slug}.admin.schoolms.com.gh` → school admin portal
  - `{slug}.schoolms.com.gh` → public school website
- **Data isolation**: `HasTenantScope` trait auto-applies `WHERE tenant_id = ?` on all school-data models via Eloquent global scope
- **Bypass**: `Model::withoutTenantScope()` for super admin queries

### Roles & Auth
- Single `users` table with `role` enum: `super_admin | school_admin`
- `tenant_id` nullable FK (null for super admins)
- Custom middleware: `EnsureSchoolAdmin`, `EnsureSuperAdmin`
- Login: `LoginController` handles both domains by detecting `superadmin.` prefix

### Teacher Portal
- Route prefix: `{slug}.schoolms.com.gh/teacher/*`
- Auth: session key `teacher_portal_id` (no Laravel Auth guard — teachers aren't in `users`)
- Middleware: `EnsureTeacherPortalAuth` — checks session, verifies `portal_active = true`
- Features: login/logout, dashboard (my classes + announcements), score entry (own subjects only), timetable
- Admin management: activate/deactivate/reset-password from Teacher show page
- Fields on `teachers` table: `portal_password` (bcrypt), `portal_active` (bool), `portal_last_login`

### Parent Portal
- Route prefix: `{slug}.schoolms.com.gh/portal/*`
- Auth: session key `parent_portal_student_id` (stateless lookup upgraded to session-based)
- Flow: POST `/portal` with admission number + DOB → stores student ID in session → redirects to `/portal/dashboard`
- Dashboard (`GET /portal/dashboard`): shows fees, attendance, assessments for the session student
- Logout: `POST /portal/logout` clears session

### Backup & Data Export
- Route group: `GET /backup`, `POST /backup`, `GET /backup/{id}/download`, `DELETE /backup/{id}`
- Trigger: `POST /backup` → creates `SchoolExport` (status=pending) → dispatches `ExportSchoolDataJob` to queue
- Duplicate guard: if a `pending` or `processing` export already exists for the tenant, redirects with `info` flash (no new job)
- Job: `App\Jobs\ExportSchoolDataJob` — builds ZIP with 7 files (`README.txt`, `students.csv`, `teachers.csv`, `classes.csv`, `attendance.csv`, `assessments.csv`, `fees.csv`) using PHP `ZipArchive` + `fputcsv`; saves to `storage/app/exports/{tenant_id}_{uuid}.zip`
- Expiry: 24 hours from `ready_at`; download checks both `status=ready` and `expires_at > now()`
- Tenant isolation: `BackupController` scopes all queries by `currentTenant->id`; other tenant's exports return 404
- Job uses `withoutGlobalScopes()->where('tenant_id', $tenantId)` to bypass `HasTenantScope` (tenant is bound manually via `app()->instance()` at job start)
- Queue requirement: exports only process if `php artisan queue:work` is running (noted in UI)

### REST API (v1)
- Package: `laravel/sanctum` (token-based auth, 30-day expiry)
- Base path: `/api/v1/` (served from root domain, no subdomain required — mobile-friendly)
- **Authentication**: `POST /api/v1/auth/login` accepts `{email, password, school_slug}` → returns `{token, user, school}`
- **Tenant resolution**: `SetTenantFromToken` middleware alias `api.tenant` — reads `auth()->user()->tenant_id`, fetches `Tenant`, binds `currentTenant` so `HasTenantScope` works identically to web routes
- **Route model binding**: NOT used for tenant-scoped models (`Student`, `SchoolClass`) — `SubstituteBindings` runs before `api.tenant`, so binding would bypass `HasTenantScope`. Routes use `{id}` integers; controllers resolve via `Model::findOrFail($id)` after tenant is bound
- **Date caveat**: Eloquent `date` cast persists via `fromDateTime()` as `Y-m-d H:i:s`; use `whereDate()` not `where('date', ...)` for SQLite date comparisons
- Endpoints: login, logout, /me, dashboard stats, students (list/show), classes (list/show), attendance (list/record), announcements, timetable, assessments
- Token scoped to school admin only; super admins are not mobile users
- **OpenAPI spec**: `public/api/openapi.yaml` (OpenAPI 3.1) — served at `/api/docs` via Swagger UI; also linked from admin nav (`/api-docs` → redirects)

### Two-Factor Authentication (TOTP)
- Package: `pragmarx/google2fa-laravel` (RFC 6238 TOTP)
- Fields on `users` table: `two_factor_secret` (string, nullable, hidden), `two_factor_enabled` (bool, default false)
- **Login flow**: `LoginController::login()` → after credential check, if `$user->two_factor_enabled`, log out, store `auth.2fa_pending_user_id` + `auth.2fa_pending_role` in session, redirect to `/2fa/challenge`
- **Challenge**: `TwoFactorController::showChallenge()` / `challenge()` — verifies TOTP code (±2 window = ±60s tolerance), then calls `Auth::login()`, sets `auth.2fa_verified = true`
- **Middleware**: `EnsureTwoFactorVerified` alias `2fa` — if user has 2FA enabled and session missing `auth.2fa_verified`, redirect to challenge route
- **Route placement**: `['school.admin', '2fa', 'subscription.admin']` for admin; `['super.admin', '2fa']` for superadmin
- **Setup**: `GET /settings/2fa` generates secret in session → QR via Google Charts API → `POST /settings/2fa/confirm` verifies code and persists secret
- **Disable**: `POST /settings/2fa/disable` requires `current_password`, clears secret + flag
- Challenge routes are outside the protected middleware group (accessible post-login but pre-2fa)

### Subscription Lifecycle
States: `trial → active → grace → locked → suspended`
- Transitions run daily via `CheckSubscriptionStatusJob` scheduled at **06:00 WAT**
- Grace period: 5 days after term end (configurable via `BILLING_GRACE_DAYS`)
- Grace triggers non-dismissible banner on admin; dismissible banner on public site
- Locked: admin sees lock screen, public sees website lock screen
- Activation: instantaneous on webhook receipt from Paystack or Flutterwave

### Payment Gateways
- **Paystack**: API-based init (`POST /transaction/initialize`) → `authorization_url`
- **Moolre**: API-based init (`POST https://api.moolre.com/embed/src/start` with `state: starter`) → `authorization_url`; verify with same endpoint (`state: confirm`); webhook uses HMAC-SHA256 signature (`X-Moolre-Signature` header)
- Webhook verification: Paystack uses HMAC-SHA512 (`X-Paystack-Signature`); Moolre uses HMAC-SHA256 (`X-Moolre-Signature`)
- Idempotency: check `payment.isSuccess()` before processing duplicate webhooks
- Routes exempt from CSRF: `/webhooks/paystack`, `/webhooks/moolre`

## Key Files

### Config
- `config/app.php` → `app.domain` key (reads `APP_DOMAIN` env var)
- `config/billing.php` → grace days, gateway, plan prices
- `config/services.php` → Paystack, Flutterwave, Hubtel credentials

### Models with `HasTenantScope`
Student, Teacher, SchoolClass, Subject, Attendance, Assessment, ReportCard, Fee, Admission

### Models WITHOUT `HasTenantScope` (global data)
Tenant, AcademicYear, AcademicTerm, Subscription, Payment, SubscriptionNotification

### Routes
- `routes/web.php` — domain groups (super admin, school admin, public website) + payment + webhooks
- `routes/admin.php` — all school admin protected routes (109 total across the app)
- `routes/superadmin.php` — super admin protected routes
- `routes/website.php` — public school pages + teacher portal + parent portal
- `routes/console.php` — scheduled job registration

## Ghana GES Grading (GradeCalculator)
```
A1 (80-100) Excellent   → 1 point
B2 (70-79)  Very Good   → 2 points
B3 (60-69)  Good        → 3 points
C4 (55-59)  Credit      → 4 points
C5 (50-54)  Credit      → 5 points
C6 (45-49)  Credit      → 6 points
D7 (40-44)  Pass        → 7 points
E8 (35-39)  Pass        → 8 points
F9 (0-34)   Fail        → 9 points
```
- Scores floored to integer before comparison
- Aggregate: sum of best-6 subject points (lower = better, BECE style)
- Passing threshold: C6 or better (points ≤ 6)

## Demo Accounts (after `php artisan db:seed`)
| Role        | Email                          | Password           |
|-------------|--------------------------------|--------------------|
| Super Admin | superadmin@schoolms.gh         | SuperAdmin@123     |
| School Admin| admin@accraacademy.edu.gh      | AccraAdmin@123     |
| School Admin| admin@kumasiacademy.edu.gh     | KumasiAdmin@123    |
| School Admin| admin@cca.edu.gh               | CapeCoastAdmin@123 |

## Development Setup
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

## Testing
```bash
php artisan test                                    # All 545 tests (green)
php artisan test tests/Unit/GradeCalculatorTest.php # Grade logic (25 cases)
php artisan test tests/Unit/PolicyTest.php          # Authorization policies (20 cases)
php artisan test tests/Unit/ExportSchoolDataJobTest.php    # ZIP export job (6 cases)
php artisan test tests/Feature/Admin               # Admin HTTP feature tests
php artisan test tests/Feature/Admin/BackupExportTest.php  # Backup & export HTTP tests (9 cases)
php artisan test tests/Feature/Auth                # Auth login tests
php artisan test tests/Feature/SuperAdmin          # Super admin tests
php artisan test tests/Feature/Website             # Website + admissions tests
php artisan test tests/Feature/SubscriptionStateTest.php   # Subscription transitions
php artisan test tests/Feature/WebhookIdempotencyTest.php  # Webhook security
php artisan test tests/Feature/Auth/TwoFactorAuthTest.php  # 2FA login + setup + disable (16 cases)
php artisan test tests/Feature/Api                         # REST API tests (25 cases)
php artisan subscriptions:lifecycle --dry-run       # Preview daily lifecycle job
```

### Unit Test Isolation Note
`ExportSchoolDataJobTest` uses `year_label = 'Export-Unit-Test-Year'` (not `2024/2025`) to avoid
a unique constraint collision with `AdminTestCase`, which also creates `AcademicYear('2024/2025')`
in its `setUp()`. Both classes use `RefreshDatabase`, but when run in the same process they share
the in-memory SQLite connection; using a distinct label keeps them fully independent.

### Admin Test Infrastructure
All admin HTTP tests extend `tests/Feature/Admin/AdminTestCase.php`, which:
- Creates: `AcademicYear`, `AcademicTerm`, `Tenant` (slug=`test-school`), `Subscription` (active), `User` (school_admin)
- Calls `URL::forceRootUrl("http://test-school.admin.{APP_DOMAIN}")` so `get('/dashboard')` targets the domain route group
- Calls `URL::defaults(['slug' => 'test-school'])` so Blade `route()` calls inside domain-group views resolve without explicit slug

### Domain Routing in Tests (Laravel 13)
`MakesHttpRequests::prepareUrlForRequest()` uses `url($uri)`, not `$this->baseUrl`. Setting `$this->baseUrl` has no effect. The only working approach is `URL::forceRootUrl()`.

### Domain Parameter Injection Bug (Fixed)
`RouteParameterBinder::bindHostParameters` merges host params (slug) BEFORE path params via `array_merge(HOST, PATH)`. This caused `{slug}` to be injected as positional argument #1 into every controller that takes a route-bound model. **Fix**: `ResolveTenantMiddleware` calls `$request->route()->forgetParameter('slug')` after resolving the tenant, removing the domain param before controller injection happens.

## Queue Architecture

Three dedicated Horizon supervisors with separate job queues:

| Queue | Supervisor | Max Workers | Timeout | Use |
|-------|-----------|-------------|---------|-----|
| `default` | supervisor-default | 8 (prod) / 2 (local) | 90 s | Notifications, SMS, emails, misc |
| `pdf` | supervisor-pdf | 4 (prod) / 1 (local) | 180 s | `GenerateReportCardJob` |
| `exports` | supervisor-exports | 2 (prod) / 1 (local) | 300 s | `ExportSchoolDataJob` |

Jobs are dispatched to their queue automatically via `$this->onQueue(...)` in the constructor. Workers run under Laravel Horizon (`php artisan horizon`).

### Horizon Dashboard
- URL: `/horizon`
- Production: requires `role === 'super_admin'`
- Local/testing: open to all (no auth)
- Metrics history requires `horizon:snapshot` scheduled every 5 min (configured in `routes/console.php`)

## Email Notifications

Outgoing emails dispatched to the queue (not sent synchronously):

| Trigger | Mailable | Recipient |
|---------|----------|-----------|
| Student enrolled via admission | `StudentEnrolledMail` | Guardian email |
| Report card PDF generated | `ReportCardReadyMail` | Guardian email |

Both mailables use Markdown templates in `resources/views/emails/`.
Mail failures are logged (not re-thrown) so they don't break the parent flow.

## Error Monitoring (Sentry)

Sentry is installed (`sentry/sentry-laravel`). Set `SENTRY_LARAVEL_DSN` in `.env` to activate.
Config published to `config/sentry.php`. Traces at 10%, profiles at 10% (adjustable via env vars).

## Static Analysis (PHPStan + Larastan)

Config: `phpstan.neon` at project root. Level 5 with Larastan v3 extension.

```bash
php vendor/bin/phpstan analyse app --no-ansi
```

**Windows note**: PHPStan with Larastan cannot produce captured output on Windows ZTS PHP (process isolation incompatibility). Run from a real terminal or rely on CI/CD.

**CI**: PHPStan runs automatically on every push via `.github/workflows/ci.yml` (Linux, NTS PHP 8.4).

## Database Performance Indexes

Migration `2026_05_14_100000_add_performance_indexes.php` adds composite indexes for the multi-tenant query pattern (`WHERE tenant_id = ? AND <filter>`):

- `students`: `(tenant_id, status)`, `(tenant_id, school_class_id, status)`
- `teachers`: `(tenant_id, status)`
- `fees`: `(tenant_id, student_id, status)`, `(tenant_id, due_date)`
- `assessments`: `(tenant_id, student_id, term_id)`
- `report_cards`: `(tenant_id, school_class_id, term_id)`
- `admissions`: `(tenant_id, status)`, `(tenant_id, term_id)`
- `biometric_logs`: `(device_id, is_processed)`, `(device_id, verified_at)`

## Health Check

`GET /health` — returns JSON with DB, cache, and queue status.
- `200` `{"status":"ok"}` when all checks pass
- `503` `{"status":"degraded","checks":{...}}` on failure

Used by load balancers, uptime monitors, and Kubernetes liveness probes.

## Soft Deletes

All critical data models use `SoftDeletes` — `delete()` sets `deleted_at` instead of removing the row:

| Model | Soft Deletes |
|-------|-------------|
| Student | ✅ |
| Teacher | ✅ |
| Fee | ✅ |
| Assessment | ✅ |
| Admission | ✅ |

To restore a soft-deleted record: `Model::withTrashed()->find($id)->restore()`
To hard-delete: `Model::withTrashed()->find($id)->forceDelete()`

## Audit Observer Coverage

`AuditObserver` is registered on: `Student`, `Teacher`, `Fee`, `Assessment`, `Admission`, `SchoolClass`, `Subject`.
Writes to `audit_logs` table on create/update/delete. Skipped in tests (`audit.enabled_in_tests`).

## Rate Limiting Summary

| Route | Limit |
|-------|-------|
| POST admin/super-admin login | 6/min |
| POST 2FA challenge | 10/min |
| POST teacher portal login | 6/min |
| POST parent portal lookup | 6/min |
| POST forgot-password | 5/min |
| POST reset-password | 5/min |
| GET /health | 60/min |
| Payment initiate/page | 10/min |

## Database Backup

`php artisan db:backup` — dumps MySQL to `storage/app/backups/db_<timestamp>.sql.gz`
- Runs daily at 02:00 WAT via scheduler
- Rotates files older than 7 days automatically (`--keep=N` to override)
- `--dry-run` flag shows the command without executing
- Requires `mysqldump` in `$PATH` on the server

## Deployment Checklist
- [ ] Set `APP_DOMAIN` to your root domain
- [ ] Set `SESSION_DOMAIN=.yourdomain.com` (leading dot for subdomain sharing)
- [ ] Configure MySQL database credentials
- [ ] Set `PAYSTACK_SECRET_KEY` and `PAYSTACK_PUBLIC_KEY`
- [ ] Set `MOOLRE_ACCOUNT_NUMBER` and `MOOLRE_PUBLIC_KEY`
- [ ] Configure SMTP mail (Mailgun / Postmark / SES)
- [ ] Set `SENTRY_LARAVEL_DSN` to your Sentry project DSN
- [ ] Set `HORIZON_NAME="${APP_NAME}"` (for Horizon dashboard title)
- [ ] Ensure `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` in production `.env`
- [ ] Run `php artisan storage:link`
- [ ] Run `php artisan migrate` (applies all migrations including soft-delete columns)
- [ ] Set up Horizon: copy `deploy/supervisor/schoolms-horizon.conf` or `deploy/systemd/schoolms-horizon.service` (see `deploy/README.md`)
- [ ] Set up cron: `* * * * * www-data php /var/www/schoolms/artisan schedule:run`
- [ ] Point Paystack webhook to: `https://yourdomain.com/webhooks/paystack`
- [ ] Point Moolre webhook to: `https://yourdomain.com/webhooks/moolre`
- [ ] Verify `/health` returns `{"status":"ok"}` after deploy
- [ ] Ensure `mysqldump` is in `$PATH` for daily DB backups
