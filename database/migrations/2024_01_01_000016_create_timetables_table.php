<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('day_of_week');    // 1=Mon … 5=Fri
            $table->tinyInteger('period_number');  // 1-9
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label')->nullable();   // e.g. "Break", "Assembly"
            $table->timestamps();

            $table->unique(['school_class_id', 'day_of_week', 'period_number'], 'timetable_slot_unique');
            $table->index(['tenant_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetables');
    }
};
