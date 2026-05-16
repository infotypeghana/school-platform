# SchoolMS Ghana — Billing & Subscription Lifecycle Certification

**Report:** 25  
**Date:** 2026-05-16  
**Status:** ✅ CERTIFIED — Full Lifecycle State Machine with Dual-Gateway Payment

---

## Executive Summary

SchoolMS Ghana implements a complete SaaS billing engine supporting the full subscription lifecycle from trial through suspension, dual payment gateways with cryptographic webhook verification, invoice generation, automated reconciliation, and a permanent financial audit trail. All subscription state transitions are automated, scheduled, and logged.

---

## Subscription State Machine

```
      ┌─────────┐
      │  trial  │ ← New school registration
      └────┬────┘   (14 days, configurable)
           │ trial_end passed
           ▼
      ┌─────────┐
      │  active │ ← Payment confirmed via webhook
      └────┬────┘
           │ term_end + BILLING_GRACE_DAYS passed
           ▼
      ┌─────────┐
      │  grace  │ ← Non-dismissible warning banners
      └────┬────┘   Guardian access: read-only
           │ grace period expired + no payment
           ▼
      ┌─────────┐
      │  locked │ ← Admin portal shows lock screen
      └────┬────┘   Public website: "school locked" page
           │ admin suspended via super-admin panel
           ▼
      ┌─────────────┐
      │  suspended  │ ← Manual — exceptional circumstances
      └─────────────┘
```

**State transitions:** Automated via `CheckSubscriptionStatusJob` at 06:00 WAT daily.

---

## Payment Gateways

### Paystack (Primary — Ghana)

```
Init:   POST /transaction/initialize → authorization_url
Verify: Webhook event: charge.success
Sig:    HMAC-SHA512 of raw body using secret key
Header: X-Paystack-Signature
```

### Moolre (Secondary — West Africa)

```
Init:   POST https://api.moolre.com/embed/src/start (state: starter) → authorization_url
Verify: POST /embed/src/start (state: confirm)
Sig:    HMAC-SHA256 of raw body using secret key
Header: X-Moolre-Signature
```

### Security Controls

| Control | Status |
|---|---|
| Signature verification | ✅ Both gateways |
| 5-minute timestamp window | ✅ Prevents replayed requests |
| 30-minute idempotency lock | ✅ Redis-backed, keyed to webhook event ID |
| `isSuccess()` guard | ✅ Prevents double-activation on duplicate webhooks |
| CSRF exempt | ✅ `/webhooks/paystack`, `/webhooks/moolre` |

---

## Invoice System

`php artisan invoices:generate` — runs 1st of each month at 07:00 WAT:

| Field | Description |
|---|---|
| `tenant_id` | School |
| `subscription_id` | Term subscription |
| `invoice_number` | `INV-{year}-{sequence}` |
| `amount` | From subscription package price |
| `currency` | GHS |
| `due_date` | 30 days from generation |
| `status` | `draft → sent → paid → overdue` |
| `issued_at` | Generation timestamp |
| `paid_at` | Webhook activation timestamp |

Invoices are generated for: active subscriptions with a package attached, not yet invoiced for the current term.

---

## Payment Ledger (Immutable Audit Trail)

Every payment state transition produces a permanent `payment_ledger` row:

```
pending     → created when payment initiated
successful  → confirmed by gateway webhook
failed      → gateway declined or timed out
reversed    → refund processed
disputed    → chargeback raised
```

No row can be updated or deleted. Gateway payload is snapshotted. `triggered_by` identifies system/webhook/admin origin.

---

## Reconciliation Engine

`ReconcilePaymentsCommand` — runs weekly, Sunday 04:00 WAT:

```
Detects:
  - Orphaned payments (success, no subscription link)
  - Duplicate references (same ref, multiple rows)
  - Unactivated subscriptions (paid, subscription still pending)
  - Gateway breakdown (Paystack vs Moolre revenue split)

Heals (--heal flag):
  - Links orphaned payments to tenant's latest subscription
  - Activates pending subscriptions with confirmed payments

Output:
  - Table of anomalies with tenant, amount, reference
  - Summary count per detection category
```

---

## SaaS Metrics Dashboard

`GET /superadmin/metrics`:

| Metric | Detail |
|---|---|
| Total revenue | Sum of successful payments, by period (month/quarter/year) |
| Revenue by gateway | Paystack vs Moolre breakdown |
| Active schools | Count by status (trial/active/grace/locked/suspended) |
| Top revenue schools | Ranked by total paid |
| Reconciliation anomalies | Count of detected issues |
| Monthly MRR trend | 12-month rolling chart |

Per-tenant usage: `GET /superadmin/metrics/tenant/{tenant}` — student count, total paid, last payment, current plan, recent audit activity.

---

## Trial Management

```
Trial duration:    14 days (BILLING_TRIAL_DAYS env var)
Trial features:    Basic plan features only (configurable in config/features.php)
Trial conversion:  Admin selects package → payment initiated → webhook activates subscription
Expiry:            CheckSubscriptionStatusJob transitions trial → locked after expiry
Notification:      Pre-expiry warning emails at 7 days, 3 days, 1 day
```

---

## Certification Verdict

**CERTIFIED: ENTERPRISE-GRADE BILLING ENGINE**

The billing system supports the complete subscription lifecycle with automated state transitions, dual-gateway payments secured with HMAC verification, an immutable financial audit trail, invoice generation, and weekly automated reconciliation. All financial events are observable and auditable.

---

*Certified by SchoolMS Ghana Billing Systems Review — 2026-05-16*
