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
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('portal_password')->nullable()->after('email');
            $table->boolean('portal_active')->default(false)->after('portal_password');
            $table->timestamp('portal_last_login')->nullable()->after('portal_active');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['portal_password', 'portal_active', 'portal_last_login']);
        });
    }
};
