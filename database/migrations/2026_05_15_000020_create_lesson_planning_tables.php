<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── curriculum_strands ────────────────────────────────────────────────
        // GES New Curriculum: Strand (e.g. "Number", "Geometry and Measurement")
        // Optional subject link — some strands are cross-subject.
        Schema::create('curriculum_strands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();
            $table->index(['tenant_id', 'subject_id']);
        });

        // ── curriculum_sub_strands ────────────────────────────────────────────
        Schema::create('curriculum_sub_strands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('strand_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('strand_id')->references('id')->on('curriculum_strands')->cascadeOnDelete();
            $table->index(['tenant_id', 'strand_id']);
        });

        // ── lesson_notes ──────────────────────────────────────────────────────
        // One row = one GES-format lesson note or lesson plan.
        Schema::create('lesson_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('school_class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('term_id');

            // Curriculum links (optional — teacher selects or types free-text)
            $table->unsignedBigInteger('strand_id')->nullable();
            $table->unsignedBigInteger('sub_strand_id')->nullable();

            // GES curriculum identifiers (typed by teacher)
            $table->string('content_standard')->nullable();
            $table->string('indicator_code')->nullable();    // e.g. "B4.1.1.1.1"
            $table->text('indicator')->nullable();
            $table->text('performance_indicator')->nullable();

            // Lesson metadata
            $table->string('title');
            $table->enum('type', ['lesson_note', 'lesson_plan'])->default('lesson_note');
            $table->date('week_ending')->nullable();
            $table->date('lesson_date')->nullable();
            $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])->nullable();
            $table->string('period')->nullable();            // "Period 2", "1st Period"
            $table->unsignedSmallInteger('duration')->nullable(); // minutes

            // Resources
            $table->text('reference_materials')->nullable(); // textbook refs, URLs
            $table->text('tlr')->nullable();                 // Teaching & Learning Resources
            $table->json('core_competencies')->nullable();   // ['cps', 'ci', 'cc', 'cg', 'pd', 'dl']
            $table->text('keywords')->nullable();

            // Lesson body (GES format)
            $table->longText('starter')->nullable();         // Introduction/Hook
            $table->longText('main_activities')->nullable();
            $table->longText('assessment')->nullable();      // Evaluation
            $table->longText('conclusion')->nullable();
            $table->text('homework')->nullable();

            // Approval workflow
            $table->enum('status', ['draft', 'submitted', 'approved', 'revision_requested'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('revision_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('academic_terms')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'teacher_id', 'term_id']);
            $table->index(['tenant_id', 'school_class_id', 'term_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'week_ending']);
        });

        // ── lesson_note_attachments ───────────────────────────────────────────
        Schema::create('lesson_note_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('lesson_note_id');
            $table->enum('file_type', ['pdf', 'word', 'image', 'audio', 'video', 'link']);
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();         // null for links
            $table->string('url')->nullable();               // for links; or public URL
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('lesson_note_id')->references('id')->on('lesson_notes')->cascadeOnDelete();
            $table->index(['tenant_id', 'lesson_note_id']);
        });

        // ── schemes_of_work ───────────────────────────────────────────────────
        // Termly scheme of work — one per class/subject/term.
        Schema::create('schemes_of_work', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('school_class_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('term_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->foreign('school_class_id')->references('id')->on('school_classes')->cascadeOnDelete();
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
            $table->foreign('term_id')->references('id')->on('academic_terms')->cascadeOnDelete();

            $table->index(['tenant_id', 'teacher_id', 'term_id']);
            $table->index(['tenant_id', 'school_class_id', 'subject_id', 'term_id']);
        });

        // ── scheme_of_work_weeks ──────────────────────────────────────────────
        Schema::create('scheme_of_work_weeks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scheme_of_work_id');
            $table->unsignedSmallInteger('week_number');
            $table->date('week_ending')->nullable();
            $table->string('topic');
            $table->text('learning_objectives')->nullable();
            $table->text('competencies')->nullable();
            $table->text('reference')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('scheme_of_work_id')->references('id')->on('schemes_of_work')->cascadeOnDelete();
            $table->index('scheme_of_work_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheme_of_work_weeks');
        Schema::dropIfExists('schemes_of_work');
        Schema::dropIfExists('lesson_note_attachments');
        Schema::dropIfExists('lesson_notes');
        Schema::dropIfExists('curriculum_sub_strands');
        Schema::dropIfExists('curriculum_strands');
    }
};
