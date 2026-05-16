<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Link a payment to either a subscription (existing) or a fee (new)
            $table->foreignId('fee_id')->nullable()->after('subscription_id')
                  ->constrained('fees')->nullOnDelete();

            // Distinguish subscription payments from fee payments
            $table->enum('payment_type', ['subscription', 'fee'])
                  ->default('subscription')
                  ->after('fee_id');

            $table->index(['fee_id', 'status']);
            $table->index(['payment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['fee_id']);
            $table->dropIndex(['fee_id', 'status']);
            $table->dropIndex(['payment_type', 'status']);
            $table->dropColumn(['fee_id', 'payment_type']);
        });
    }
};
