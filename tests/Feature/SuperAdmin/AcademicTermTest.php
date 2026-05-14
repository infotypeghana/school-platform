<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;

class AcademicTermTest extends SuperAdminTestCase
{
    private AcademicYear $year;
    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);

        $this->term = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 1,
            'term_name'        => 'First Term',
            'start_date'       => now()->subDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(),
            'is_current'       => true,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-terms');

        $response->assertOk();
        $response->assertViewIs('superadmin.academic-terms.index');
    }

    public function test_index_shows_existing_term(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-terms');

        $response->assertSee('First Term');
    }

    public function test_guest_is_redirected(): void
    {
        $this->asGuest()->get('/academic-terms')->assertRedirect();
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-terms/create');

        $response->assertOk();
    }

    public function test_store_creates_term(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-terms', [
            'academic_year_id' => $this->year->id,
            'term_number'      => 2,
            'term_name'        => 'Second Term',
            'start_date'       => now()->addDays(40)->toDateString(),
            'end_date'         => now()->addDays(120)->toDateString(),
            'is_current'       => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('academic_terms', ['term_name' => 'Second Term']);
    }

    public function test_store_requires_academic_year_id(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-terms', [
            'term_number' => 2,
            'term_name'   => 'Second Term',
            'start_date'  => now()->addDays(40)->toDateString(),
            'end_date'    => now()->addDays(120)->toDateString(),
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_store_requires_end_after_start(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-terms', [
            'academic_year_id' => $this->year->id,
            'term_number'      => 2,
            'term_name'        => 'Bad Term',
            'start_date'       => now()->addDays(60)->toDateString(),
            'end_date'         => now()->addDays(30)->toDateString(), // before start
        ]);

        $response->assertSessionHasErrors('end_date');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/academic-terms/{$this->term->id}/edit");

        $response->assertOk();
    }

    public function test_update_changes_term_name(): void
    {
        $response = $this->asSuperAdmin()->put("/academic-terms/{$this->term->id}", [
            'term_name'  => 'Updated First Term',
            'start_date' => $this->term->start_date->toDateString(),
            'end_date'   => $this->term->end_date->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('academic_terms', [
            'id'        => $this->term->id,
            'term_name' => 'Updated First Term',
        ]);
    }

    public function test_marking_term_as_current_clears_others(): void
    {
        $second = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 2,
            'term_name'        => 'Second Term',
            'start_date'       => now()->addDays(40)->toDateString(),
            'end_date'         => now()->addDays(120)->toDateString(),
            'is_current'       => false,
        ]);

        // Set second term as current
        $this->asSuperAdmin()->put("/academic-terms/{$second->id}", [
            'term_name'  => 'Second Term',
            'start_date' => $second->start_date->toDateString(),
            'end_date'   => $second->end_date->toDateString(),
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('academic_terms', ['id' => $second->id, 'is_current' => true]);
        $this->assertDatabaseHas('academic_terms', ['id' => $this->term->id, 'is_current' => false]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_term(): void
    {
        $id = $this->term->id;

        $this->asSuperAdmin()->delete("/academic-terms/{$id}");

        $this->assertDatabaseMissing('academic_terms', ['id' => $id]);
    }
}
