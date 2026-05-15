<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add per-student conduct & behaviour ratings to report_cards.
 *
 * conduct_ratings JSON structure:
 * {
 *   "punctuality":       "Excellent" | "Very Good" | "Good" | "Fair" | "Poor",
 *   "neatness":          "...",
 *   "attitude_to_work":  "...",
 *   "participation":     "...",
 *   "respect_for_others":"..."
 * }
 *
 * NULL means the class teacher has not yet rated the student's conduct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->json('conduct_ratings')->nullable()->after('headmaster_remark')
                ->comment('Per-student conduct ratings. NULL = not yet assessed.');
        });
    }

    public function down(): void
    {
        Schema::table('report_cards', function (Blueprint $table) {
            $table->dropColumn('conduct_ratings');
        });
    }
};
