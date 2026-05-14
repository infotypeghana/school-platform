<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            // Link to the student record created during enrolment.
            // NULL = not yet enrolled; NOT NULL = enrolled (idempotency guard).
            $table->foreignId('student_id')
                  ->nullable()
                  ->after('term_id')
                  ->constrained('students')
                  ->nullOnDelete();

            $table->timestamp('enrolled_at')->nullable()->after('submitted_at');
        });

        // Widen the status enum to include 'enrolled'.
        // SQLite (used in tests) does not enforce enum values, so we only
        // run the ALTER on MySQL / MariaDB.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE admissions
                MODIFY COLUMN status
                ENUM('pending','accepted','rejected','enrolled')
                NOT NULL DEFAULT 'pending'
            ");
        }
    }

    public function down(): void
    {
        // Revert enum first (MySQL only)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE admissions
                MODIFY COLUMN status
                ENUM('pending','accepted','rejected')
                NOT NULL DEFAULT 'pending'
            ");
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn(['student_id', 'enrolled_at']);
        });
    }
};
