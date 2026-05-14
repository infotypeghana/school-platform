<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                '14_days_before',
                '7_days_before',
                '2_days_before',
                'expiry_day',
                'grace_3_days_left',
                'grace_1_day_left',
                'grace_expired_locked',
                'payment_confirmed',
            ]);
            $table->enum('channel', ['email', 'sms', 'in_app'])->default('email');
            $table->timestamp('sent_at')->nullable();
            $table->boolean('delivered')->default(false);
            $table->timestamps();

            // Prevent duplicate notifications per type per subscription per channel
            $table->unique(['subscription_id', 'type', 'channel']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_notifications');
    }
};
