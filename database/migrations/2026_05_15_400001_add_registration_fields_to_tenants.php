<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the status enum to allow 'pending' for self-registered schools awaiting approval.
        // Raw statement required — Blueprint::enum() cannot modify an existing column.
        // SQLite (tests) silently ignores MODIFY, so this is safe in all environments.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tenants MODIFY status ENUM('pending','active','trial','grace','locked','suspended') NOT NULL DEFAULT 'trial'");
        }

        Schema::table('tenants', function (Blueprint $table) {
            // Contact person (school head / registrar)
            $table->string('contact_name')->nullable()->after('contact_email');
            // School type for billing / onboarding categorisation
            $table->string('school_type')->nullable()->after('contact_name'); // public|private|international
            // District — for reporting and support routing
            $table->string('district')->nullable()->after('school_type');
            // Estimated student count provided at registration
            $table->unsignedInteger('estimated_students')->nullable()->after('district');

            // Registration workflow
            // status already exists (trial|active|grace|locked|suspended)
            // We add 'pending' as a new valid status for unapproved registrations
            $table->timestamp('registered_at')->nullable()->after('trial_ends_at');
            $table->timestamp('approved_at')->nullable()->after('registered_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                  ->constrained('users')->nullOnDelete();
            $table->string('registration_token', 64)->nullable()->unique()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'contact_name', 'school_type', 'district',
                'estimated_students', 'registered_at', 'approved_at',
                'approved_by', 'registration_token',
            ]);
        });
    }
};
