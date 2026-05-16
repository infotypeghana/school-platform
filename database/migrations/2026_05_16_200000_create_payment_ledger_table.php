<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment ledger — immutable, append-only audit trail.
 *
 * DESIGN: Every payment state transition produces one ledger row.
 * Rows are NEVER updated or deleted — this is enforced at both the
 * DB level (no UPDATE/DELETE grants in production) and the model level
 * (PaymentLedger overrides save/update/delete to throw).
 *
 * States mirrored from Payment model plus two additional terminal states:
 *   pending → successful | failed
 *   successful → reversed | disputed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_ledger', function (Blueprint $table) {
            $table->id();

            // Source payment (NOT a hard FK — ledger must survive payment archiving)
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('tenant_id');

            // The state recorded in THIS entry
            $table->enum('state', [
                'pending',
                'successful',
                'failed',
                'reversed',
                'disputed',
            ]);

            // Financial fields snapshotted at time of entry
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('GHS');
            $table->string('gateway', 30);
            $table->string('reference', 191)->index();
            $table->string('gateway_reference', 191)->nullable();
            $table->enum('payment_type', ['subscription', 'fee'])->default('subscription');

            // Who/what triggered this state change
            $table->string('triggered_by', 60)->default('system');  // system | webhook | admin | job
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->string('trigger_reason', 255)->nullable();       // free-text reason for reversal/dispute

            // Raw gateway payload snapshot (immutable evidence)
            $table->json('gateway_payload')->nullable();

            // Immutable creation timestamp (no updated_at — this table never updates)
            $table->timestamp('recorded_at')->useCurrent();

            // Lookup indexes
            $table->index(['tenant_id', 'state']);
            $table->index(['payment_id']);
            $table->index(['tenant_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_ledger');
    }
};
