<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->string('plan_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamp('grace_ends_at')->nullable();
            $table->enum('status', ['trial', 'active', 'grace', 'locked', 'suspended'])->default('trial');
            $table->boolean('is_trial')->default(false);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
            $table->index('end_date');
            $table->index('grace_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
