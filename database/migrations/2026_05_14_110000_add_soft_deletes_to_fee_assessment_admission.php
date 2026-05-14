<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add deleted_at (soft-delete) columns to Fee, Assessment, and Admission.
 *
 * Student and Teacher already have SoftDeletes from earlier migrations.
 * This extends the same safety net to the remaining critical data models
 * so that accidental or malicious destroys are recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
