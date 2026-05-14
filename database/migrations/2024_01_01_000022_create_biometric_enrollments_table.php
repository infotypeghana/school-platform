<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->string('device_user_id');           // numeric ID assigned on the device
            $table->enum('person_type', ['student', 'teacher']);
            $table->unsignedBigInteger('person_id');    // FK to students or teachers (polymorphic)
            $table->timestamps();

            $table->unique(['device_id', 'device_user_id'], 'enrollment_device_user_unique');
            $table->unique(['device_id', 'person_type', 'person_id'], 'enrollment_person_unique');
            $table->index(['tenant_id', 'person_type', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_enrollments');
    }
};
