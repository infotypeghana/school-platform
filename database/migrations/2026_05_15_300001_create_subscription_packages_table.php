<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                  // e.g. "Starter", "Growth", "School+"
            $table->string('slug')->unique();                        // e.g. "starter"
            $table->text('description')->nullable();
            $table->decimal('price_per_student', 10, 2);            // GHS per student per term
            $table->unsignedInteger('min_students')->default(50);    // minimum billable students
            $table->enum('billing_cycle', ['term', 'annual'])->default('term');
            $table->json('features')->nullable();                    // ["Attendance", "Report Cards", ...]
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
