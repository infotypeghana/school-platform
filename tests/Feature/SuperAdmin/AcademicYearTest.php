<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\AcademicYear;

class AcademicYearTest extends SuperAdminTestCase
{
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->year = AcademicYear::create([
            'year_label' => '2023/2024',
            'is_current' => false,
        ]);
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-years');

        $response->assertOk();
        $response->assertViewIs('superadmin.academic-years.index');
    }

    public function test_index_shows_existing_year(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-years');

        $response->assertSee('2023/2024');
    }

    public function test_guest_is_redirected(): void
    {
        $this->asGuest()->get('/academic-years')->assertRedirect();
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get('/academic-years/create');

        $response->assertOk();
    }

    public function test_store_creates_academic_year(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-years', [
            'year_label' => '2025/2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('academic_years', ['year_label' => '2025/2026']);
    }

    public function test_store_rejects_duplicate_year_label(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-years', [
            'year_label' => '2023/2024', // already exists
        ]);

        $response->assertSessionHasErrors('year_label');
    }

    public function test_store_requires_year_label(): void
    {
        $response = $this->asSuperAdmin()->post('/academic-years', []);

        $response->assertSessionHasErrors('year_label');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_form_returns_200(): void
    {
        $response = $this->asSuperAdmin()->get("/academic-years/{$this->year->id}/edit");

        $response->assertOk();
    }

    public function test_update_changes_year_label(): void
    {
        $response = $this->asSuperAdmin()->put("/academic-years/{$this->year->id}", [
            'year_label' => '2023/2024 Revised',
            'is_current' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('academic_years', [
            'id'         => $this->year->id,
            'year_label' => '2023/2024 Revised',
        ]);
    }

    public function test_marking_year_as_current_clears_others(): void
    {
        $second = AcademicYear::create(['year_label' => '2024/2025', 'is_current' => true]);

        // Mark the first year as current
        $this->asSuperAdmin()->put("/academic-years/{$this->year->id}", [
            'year_label' => '2023/2024',
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('academic_years', ['id' => $this->year->id, 'is_current' => true]);
        $this->assertDatabaseHas('academic_years', ['id' => $second->id, 'is_current' => false]);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_year(): void
    {
        $id = $this->year->id;

        $this->asSuperAdmin()->delete("/academic-years/{$id}");

        $this->assertDatabaseMissing('academic_years', ['id' => $id]);
    }
}
