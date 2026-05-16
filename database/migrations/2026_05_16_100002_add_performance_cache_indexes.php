<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additional indexes for reconciliation, lockout, and audit queries.
 * Uses try/catch so duplicate-index errors are silently skipped on any driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->safeIndex('payments', fn (Blueprint $t) => $t->index(['status', 'created_at'], 'payments_status_created_at_index'));
        $this->safeIndex('payments', fn (Blueprint $t) => $t->index('reference', 'payments_reference_index'));
        $this->safeIndex('payments', fn (Blueprint $t) => $t->index(['tenant_id', 'status'], 'payments_tenant_id_status_index'));
        $this->safeIndex('subscriptions', fn (Blueprint $t) => $t->index(['status', 'tenant_id'], 'subscriptions_status_tenant_id_index'));
        $this->safeIndex('audit_logs', fn (Blueprint $t) => $t->index(['tenant_id', 'created_at'], 'audit_logs_tenant_created_index'));
        $this->safeIndex('users', fn (Blueprint $t) => $t->index('email', 'users_email_index'));
    }

    public function down(): void
    {
        $drops = [
            'payments'      => ['payments_status_created_at_index', 'payments_reference_index', 'payments_tenant_id_status_index'],
            'subscriptions' => ['subscriptions_status_tenant_id_index'],
            'audit_logs'    => ['audit_logs_tenant_created_index'],
            'users'         => ['users_email_index'],
        ];

        foreach ($drops as $table => $indexes) {
            foreach ($indexes as $index) {
                try {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                } catch (\Throwable) {
                    // Index may not exist if up() was partially run
                }
            }
        }
    }

    private function safeIndex(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Throwable) {
            // Index already exists — skip silently
        }
    }
};
