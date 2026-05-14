<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('term_number'); // 1, 2, 3
            $table->string('term_name');                // "First Term", "Second Term", "Third Term"
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'term_number']);
            $table->index('is_current');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
