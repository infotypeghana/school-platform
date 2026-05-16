# SchoolMS Ghana — Feature Gating System Documentation

**Generated:** 2026-05-16

---

## Overview

The feature gating system controls access to platform features based on the tenant's subscription plan tier. It operates at three layers: Blade templates (UI hiding), HTTP middleware (route blocking), and service-layer enforcement (controller abort).

---

## 1. Architecture

```
config/features.php          ← Feature definitions + tier mappings
          │
app/Services/FeatureGate.php ← Central service (singleton per request)
          │
     ┌────┴───────────────────────────────────┐
     │                                         │
@feature('key')                  'feature:key' middleware
(Blade directive — UI hiding)    (HTTP middleware — route blocking)
                                              │
                             FeatureGate::require('key')
                             (controller abort — programmatic)
```

---

## 2. Feature Definitions (`config/features.php`)

```php
'definitions' => [
    'feature_key' => [
        'label'       => 'Human Readable Name',
        'min_tier'    => 'basic|standard|premium|enterprise',
        'description' => 'Shown in upgrade prompts.',
    ],
    ...
]
```

**Tier hierarchy (ascending):** `trial (0) < basic (1) < standard (2) < premium (3) < enterprise (4)`

A tenant on the `premium` tier automatically has access to all `basic` and `standard` features.

---

## 3. Usage

### 3.1 Blade Templates (UI hiding)

```blade
{{-- Show button only if tenant has SMS feature --}}
@feature('sms_notifications')
    <button>Send SMS</button>
@else
    <div class="opacity-50 cursor-not-allowed" title="Upgrade to Standard plan">
        📨 SMS (Standard plan)
    </div>
@endfeature
```

### 3.2 Route Middleware

```php
// routes/admin.php
Route::group(['middleware' => 'feature:biometric'], function () {
    Route::resource('/biometric', BiometricController::class);
});

Route::get('/sms', [SmsController::class, 'index'])
    ->middleware('feature:sms_notifications');
```

On failure: redirects back with `feature_locked` flash message containing upgrade instructions. API requests receive `403 JSON`.

### 3.3 Controller Enforcement

```php
public function __construct(private FeatureGate $gate) {}

public function store(Request $request): RedirectResponse
{
    $this->gate->require('bulk_sms'); // throws 403 if not enabled
    // ... rest of handler
}
```

### 3.4 Service Layer

```php
// Check without aborting
if (app(FeatureGate::class)->enabled('report_cards_pdf')) {
    dispatch(new GenerateReportCardJob($student));
} else {
    // basic report card only
}

// Check for a specific tenant (not the current request tenant)
$gate->for($otherTenant)->enabled('api_access');
```

### 3.5 Limit Helpers

```php
$gate = app(FeatureGate::class);

$gate->storageLimit();  // MB limit for current tenant (100|500|2000|10000|50000)
$gate->studentLimit();  // max students (50|500|1000|5000|0=unlimited)
$gate->smsLimit();      // SMS credits per term (50|0|500|2000|0=unlimited)
```

---

## 4. Full Feature List

| Feature Key | Label | Min Tier |
|---|---|---|
| `students` | Student Management | basic |
| `teachers` | Teacher Management | basic |
| `classes` | Class Management | basic |
| `attendance` | Attendance Tracking | basic |
| `fees` | Fee Management | basic |
| `admissions` | Admissions Portal | basic |
| `announcements` | Announcements | basic |
| `timetable` | Timetable | basic |
| `parent_portal` | Parent Portal | basic |
| `website` | School Website | basic |
| `report_cards` | Report Cards (Basic) | basic |
| `sms_notifications` | SMS Notifications | standard |
| `assessments` | Online Assessments | standard |
| `lesson_notes` | Lesson Notes & Plans | standard |
| `feeding_fees` | Feeding Fee Management | standard |
| `promotions` | Class Promotions | standard |
| `data_export` | Data Export & Backup | standard |
| `analytics` | Basic Analytics | standard |
| `curriculum` | Curriculum Management | standard |
| `report_cards_pdf` | PDF Report Cards | premium |
| `biometric` | Biometric Attendance | premium |
| `api_access` | REST API Access | premium |
| `custom_branding` | Custom Branding | premium |
| `teacher_portal` | Teacher Portal | premium |
| `advanced_analytics` | Advanced Analytics | premium |
| `bulk_sms` | Bulk SMS Campaigns | premium |
| `payroll` | Staff Payroll | enterprise |
| `multi_branch` | Multi-Branch Support | enterprise |
| `custom_domain` | Custom Domain | enterprise |
| `dedicated_support` | Dedicated Support | enterprise |
| `audit_log_export` | Audit Log Export | enterprise |
| `sso` | Single Sign-On (SSO) | enterprise |

---

## 5. Cache Management

Feature checks are cached 10 minutes per tenant per feature key:

```
Cache key: "feature:{tenant_id}:{feature_key}"
Tier key:  "feature_tier:{tenant_id}"
TTL: 10 minutes
```

**Manual flush after subscription change:**
```php
app(FeatureGate::class)->flushCache($tenant);
// Called automatically by SubscriptionService::flushCache()
```

---

## 6. Adding a New Feature

1. Add entry to `config/features.php` under `definitions`
2. Set `min_tier` to the appropriate tier
3. Add `@feature('new_key')` in Blade templates where needed
4. Add `->middleware('feature:new_key')` to protected routes
5. Run `php artisan config:clear` to clear config cache

No database changes required — features are config-driven.

---

## 7. Tier Resolution Logic

The FeatureGate resolves a tenant's tier from their current subscription:

```
No subscription → trial
is_trial = true → trial
status = locked/suspended → basic (reduced access while locked)
package.slug contains 'enterprise' → enterprise
package.slug contains 'premium' → premium
package.slug contains 'standard'|'growth' → standard
package.slug contains 'basic'|'starter' → basic
```

---

*Generated by SchoolMS Ghana Enterprise Audit — 2026-05-16*
