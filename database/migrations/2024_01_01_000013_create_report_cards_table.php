<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('academic_terms');

            $table->unsignedSmallInteger('total_subjects')->default(0);
            $table->unsignedSmallInteger('overall_position')->nullable();
            $table->unsignedSmallInteger('out_of')->nullable();      // class size

            $table->text('class_teacher_remark')->nullable();
            $table->text('headmaster_remark')->nullable();

            $table->unsignedSmallInteger('attendance_present')->default(0);
            $table->unsignedSmallInteger('attendance_total')->default(0);

            $table->timestamp('generated_at')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'term_id']); // one per student per term
            $table->index(['tenant_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_cards');
    }
};
