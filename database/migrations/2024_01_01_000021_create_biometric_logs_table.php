<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->string('device_user_id');           // user ID on the device (integer or string)
            $table->timestamp('verified_at');           // when the device recorded it
            $table->unsignedTinyInteger('verify_type'); // 0=finger,1=finger2,4=RFID,15=face
            $table->unsignedTinyInteger('direction')->default(0); // 0=in,1=out,2=break_out,…
            $table->string('source')->default('adms');  // 'adms' or 'tcp'
            $table->boolean('is_processed')->default(false);
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Prevent double-processing the same punch
            $table->unique(['device_id', 'device_user_id', 'verified_at'], 'bio_log_unique');
            $table->index(['tenant_id', 'is_processed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_logs');
    }
};
