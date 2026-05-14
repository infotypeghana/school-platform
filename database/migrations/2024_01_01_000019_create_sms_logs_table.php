<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');             // phone number
            $table->text('message');
            $table->enum('channel', ['sms', 'whatsapp'])->default('sms');
            $table->enum('status', ['sent', 'failed', 'pending'])->default('pending');
            $table->string('provider_ref')->nullable();
            $table->text('error_message')->nullable();
            $table->string('context_type')->nullable();  // Fee, Attendance, etc.
            $table->unsignedBigInteger('context_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
