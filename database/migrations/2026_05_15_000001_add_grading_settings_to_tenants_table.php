<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-tenant grading configuration to the tenants table.
 *
 * grading_settings JSON structure:
 * {
 *   "ca_max":  30,       // Maximum marks for Continuous Assessment (default GES: 30)
 *   "exam_max": 70,      // Maximum marks for the End-of-Term Exam   (default GES: 70)
 *   "scale": [           // Array of grade bands (ascending min order)
 *     { "min": 80, "max": 100, "grade": "A1", "remark": "Excellent",  "points": 1 },
 *     { "min": 70, "max": 79,  "grade": "B2", "remark": "Very Good",  "points": 2 },
 *     ...
 *   ]
 * }
 *
 * When NULL the platform falls back to the built-in GES standard scale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('grading_settings')->nullable()->after('primary_color')
                ->comment('Per-tenant grading scale and CA/Exam weight overrides. NULL = use GES default.');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('grading_settings');
        });
    }
};
