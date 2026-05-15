<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── feeding_configs ───────────────────────────────────────────────────
        // One row = a rate configuration.
        // school_class_id = null → school-wide default
        // school_class_id = X   → per-class override (takes precedence)
        Schema::create('feeding_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->decimal('rate_per_day', 8, 2)->default(0);
            $table->enum('billing_mode', ['daily', 'weekly', 'monthly', 'termly'])->default('termly');
            $table->unsignedTinyInteger('school_days_per_week')->default(5);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->nullOnDelete();

            // Only one config per tenant per class (null = school-wide)
            $table->unique(['tenant_id', 'school_class_id']);
            $table->index(['tenant_id', 'is_active']);
        });

        // ── feeding_fees ──────────────────────────────────────────────────────
        // One row per student per term.
        Schema::create('feeding_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('school_class_id');   // denormalised — class at time of assignment
            $table->unsignedBigInteger('term_id');
            $table->enum('billing_mode', ['daily', 'weekly', 'monthly', 'termly']);
            $table->decimal('rate_per_day', 8, 2);           // snapshot at assignment time
            $table->unsignedSmallInteger('feeding_days');    // number of school days billed
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['unpaid', 'partial', 'paid', 'exempt'])->default('unpaid');
            $table->boolean('is_exempt')->default(false);
            $table->string('exemption_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('academic_terms')->cascadeOnDelete();

            // One feeding fee per student per term
            $table->unique(['tenant_id', 'student_id', 'term_id']);
            $table->index(['tenant_id', 'term_id', 'status']);
            $table->index(['tenant_id', 'student_id', 'status']);
            $table->index(['tenant_id', 'school_class_id', 'term_id']);
        });

        // ── feeding_payments ──────────────────────────────────────────────────
        // Immutable audit trail of every payment received.
        Schema::create('feeding_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('feeding_fee_id');
            $table->unsignedBigInteger('student_id');        // denormalised for fast lookups
            $table->unsignedBigInteger('term_id');           // denormalised
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('receipt_number')->unique();
            $table->enum('payment_method', ['cash', 'mobile_money', 'bank_transfer', 'cheque'])->default('cash');
            $table->unsignedBigInteger('recorded_by')->nullable();  // users.id
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('feeding_fee_id')->references('id')->on('feeding_fees')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('academic_terms')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'feeding_fee_id']);
            $table->index(['tenant_id', 'term_id']);
            $table->index(['tenant_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeding_payments');
        Schema::dropIfExists('feeding_fees');
        Schema::dropIfExists('feeding_configs');
    }
};
