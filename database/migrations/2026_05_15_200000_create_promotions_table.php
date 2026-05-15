<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            // Tenant isolation (no FK — tenants table may not exist in tests)
            $table->unsignedBigInteger('tenant_id')->index();

            // Core foreign keys
            $table->foreignId('student_id')
                  ->constrained('students')
                  ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                  ->constrained('academic_years');

            $table->foreignId('from_class_id')
                  ->constrained('school_classes');

            // NULL for graduated students (no destination class)
            $table->foreignId('to_class_id')
                  ->nullable()
                  ->constrained('school_classes');

            $table->enum('action', ['promoted', 'held_back', 'graduated', 'transferred']);

            // The admin who ran the promotion (nullable in case user is later deleted)
            $table->foreignId('promoted_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Compound indexes for typical query patterns
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'academic_year_id']);
            $table->index(['tenant_id', 'from_class_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
