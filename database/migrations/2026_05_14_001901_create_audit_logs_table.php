<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();  // admin who acted
            $table->string('user_name')->nullable();        // snapshot of name at time of action
            $table->string('action');                       // created | updated | deleted | login | export | etc.
            $table->string('auditable_type')->nullable();   // App\Models\Student
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('auditable_label')->nullable();  // human-readable: "Kofi Mensah (ADM-2024-0001)"
            $table->json('old_values')->nullable();         // before-state (for updates/deletes)
            $table->json('new_values')->nullable();         // after-state
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
