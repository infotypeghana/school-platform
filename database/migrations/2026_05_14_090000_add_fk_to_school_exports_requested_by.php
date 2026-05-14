<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add referential integrity to school_exports.requested_by.
 *
 * The column is made nullable so that deleting a user account does not
 * cascade-delete export files — instead, requested_by is nullified and
 * the export record (including its download link) is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_exports', function (Blueprint $table) {
            // Allow null so we can safely use nullOnDelete()
            $table->unsignedBigInteger('requested_by')->nullable()->change();

            $table->foreign('requested_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_exports', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->unsignedBigInteger('requested_by')->nullable(false)->change();
        });
    }
};
