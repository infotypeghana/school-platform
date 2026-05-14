<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'student_id', 'date']); // one record per student per day
            $table->index(['tenant_id', 'school_class_id', 'date']);
            $table->index(['tenant_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
