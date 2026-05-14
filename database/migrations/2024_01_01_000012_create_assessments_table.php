<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('academic_terms');

            // Ghana GES scoring: CA = max 30, Exam = max 70
            $table->decimal('ca_score', 5, 2)->default(0);
            $table->decimal('exam_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);    // computed
            $table->string('grade', 2)->nullable();               // A1–F9

            // Class statistics (computed after all scores entered)
            $table->unsignedSmallInteger('position_in_class')->nullable();
            $table->decimal('class_average', 5, 2)->nullable();
            $table->decimal('highest_score', 5, 2)->nullable();
            $table->decimal('lowest_score', 5, 2)->nullable();

            $table->timestamps();

            // One entry per student per subject per term
            $table->unique(['tenant_id', 'student_id', 'subject_id', 'term_id']);
            $table->index(['tenant_id', 'school_class_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
