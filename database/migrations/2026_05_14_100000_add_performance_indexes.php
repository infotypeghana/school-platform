<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes that the HasTenantScope query pattern relies on but
 * that were missing from the original table definitions.
 *
 * Every model with HasTenantScope runs queries shaped like:
 *   WHERE tenant_id = ? AND <filter>
 *
 * Without a composite index, MySQL falls back to the single-column tenant_id
 * index and then filters the rest in-memory — acceptable for small datasets,
 * but slow once a school has 1,000+ students or several years of data.
 *
 * These indexes are additive (they don't change any existing unique constraints
 * or foreign keys) and safe to apply on a live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── students ──────────────────────────────────────────────────────────
        Schema::table('students', function (Blueprint $table) {
            // Common query: tenant + status (active student lists everywhere)
            $table->index(['tenant_id', 'status'], 'students_tenant_status_idx');

            // Common query: class roster of active students
            $table->index(['tenant_id', 'school_class_id', 'status'], 'students_tenant_class_status_idx');
        });

        // ── teachers ──────────────────────────────────────────────────────────
        Schema::table('teachers', function (Blueprint $table) {
            // Active teacher dropdowns on class/subject forms
            $table->index(['tenant_id', 'status'], 'teachers_tenant_status_idx');
        });

        // ── fees ──────────────────────────────────────────────────────────────
        Schema::table('fees', function (Blueprint $table) {
            // Per-student fee history (student show page, parent portal)
            $table->index(['tenant_id', 'student_id', 'status'], 'fees_tenant_student_status_idx');

            // Finance dashboard: fees due within a date range
            $table->index(['tenant_id', 'due_date'], 'fees_tenant_due_date_idx');
        });

        // ── biometric_logs ────────────────────────────────────────────────────
        Schema::table('biometric_logs', function (Blueprint $table) {
            // Reprocessing query after a new enrollment is saved
            $table->index(['device_id', 'is_processed'], 'bio_logs_device_processed_idx');

            // Live feed endpoint: recent logs for a device
            $table->index(['device_id', 'verified_at'], 'bio_logs_device_time_idx');
        });

        // ── assessments ───────────────────────────────────────────────────────
        Schema::table('assessments', function (Blueprint $table) {
            // Single-student report card lookup (student_id + term_id is the
            // most common access pattern in ReportCardService::generatePdf)
            $table->index(['tenant_id', 'student_id', 'term_id'], 'assessments_tenant_student_term_idx');
        });

        // ── report_cards ──────────────────────────────────────────────────────
        Schema::table('report_cards', function (Blueprint $table) {
            // classCards() query: all report cards for a class + term
            $table->index(['tenant_id', 'school_class_id', 'term_id'], 'report_cards_tenant_class_term_idx');
        });

        // ── admissions ────────────────────────────────────────────────────────
        Schema::table('admissions', function (Blueprint $table) {
            // Status tab counts + filter queries
            $table->index(['tenant_id', 'status'], 'admissions_tenant_status_idx');

            // Term filter on admissions list
            $table->index(['tenant_id', 'term_id'], 'admissions_tenant_term_idx');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_tenant_status_idx');
            $table->dropIndex('students_tenant_class_status_idx');
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropIndex('teachers_tenant_status_idx');
        });

        Schema::table('fees', function (Blueprint $table) {
            $table->dropIndex('fees_tenant_student_status_idx');
            $table->dropIndex('fees_tenant_due_date_idx');
        });

        Schema::table('biometric_logs', function (Blueprint $table) {
            $table->dropIndex('bio_logs_device_processed_idx');
            $table->dropIndex('bio_logs_device_time_idx');
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex('assessments_tenant_student_term_idx');
        });

        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropIndex('report_cards_tenant_class_term_idx');
        });

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex('admissions_tenant_status_idx');
            $table->dropIndex('admissions_tenant_term_idx');
        });
    }
};
