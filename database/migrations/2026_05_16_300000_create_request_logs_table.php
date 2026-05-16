<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request Log table — append-only HTTP request telemetry.
 *
 * Captures every inbound HTTP request with tenant context, user identity,
 * timing, and response status. Used for:
 *   - Per-tenant usage analytics in the super admin metrics dashboard
 *   - Slow request detection (duration_ms threshold alerting)
 *   - Security incident investigation (IP + path timeline)
 *   - API quota usage visibility
 *
 * Retention: 90 days (pruned nightly by PruneRequestLogsCommand).
 * No updated_at — rows are immutable once written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();

            // ── Request identity ───────────────────────────────────────────────
            // UUID generated per-request; bound to IoC so child log entries
            // (e.g. audit_logs, payment_ledger) can cross-reference the same request.
            $table->uuid('request_id')->index();

            // ── Tenant & user context ─────────────────────────────────────────
            // Nullable: unauthenticated requests, super admin routes, and health
            // checks have no tenant. Super admin users have no tenant_id.
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();

            // Differentiates session types: school_admin | super_admin |
            // teacher_portal | parent_portal | api | unauthenticated
            $table->string('user_type', 30)->nullable();

            // ── HTTP details ──────────────────────────────────────────────────
            $table->string('method', 10);          // GET, POST, PUT, DELETE, PATCH
            $table->string('path', 500);            // /admin/students, /api/v1/students
            $table->string('route_name', 200)->nullable(); // admin.students.index
            $table->unsignedSmallInteger('status_code');    // 200, 302, 403, 404, 500

            // ── Performance ───────────────────────────────────────────────────
            $table->float('duration_ms', 8, 2);    // Wall-clock time (not CPU)

            // ── Client metadata ───────────────────────────────────────────────
            $table->string('ip_address', 45);      // Supports IPv6
            $table->text('user_agent')->nullable();

            // ── Timestamp (no updated_at — immutable) ─────────────────────────
            $table->timestamp('created_at')->useCurrent();

            // ── Composite indexes for common query patterns ───────────────────
            // Super admin dashboard: filter by tenant, time range
            $table->index(['tenant_id', 'created_at']);
            // Slow request detection: filter by duration across all tenants
            $table->index(['duration_ms', 'created_at']);
            // Status code monitoring: 5xx rate by tenant
            $table->index(['tenant_id', 'status_code', 'created_at']);
            // API usage: filter by user_type = 'api'
            $table->index(['user_type', 'tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
