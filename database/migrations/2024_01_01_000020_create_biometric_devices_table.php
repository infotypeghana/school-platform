<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // friendly label, e.g. "Main Gate"
            $table->string('device_serial')->nullable(); // ZKTeco serial number (for ADMS)
            $table->string('ip_address')->nullable();   // for TCP pull
            $table->unsignedSmallInteger('port')->default(4370);
            $table->unsignedInteger('password')->default(0); // device password (0 = none)
            $table->string('model')->nullable();        // e.g. "ZKTeco K40"
            $table->string('location')->nullable();     // e.g. "Main Gate"
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'device_serial']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
