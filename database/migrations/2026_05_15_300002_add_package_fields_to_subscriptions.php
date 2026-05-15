<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Package FK — nullable so legacy/trial subscriptions don't break
            $table->foreignId('package_id')
                  ->nullable()
                  ->after('plan_id')
                  ->constrained('subscription_packages')
                  ->nullOnDelete();

            // Snapshot values at invoice time — stored so price changes don't alter history
            $table->unsignedInteger('student_count')->nullable()->after('package_id');
            $table->decimal('price_per_student', 10, 2)->nullable()->after('student_count');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn(['student_count', 'price_per_student']);
        });
    }
};
