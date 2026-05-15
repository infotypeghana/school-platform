<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;

class CalendarSettingsTest extends AdminTestCase
{
    private AcademicTerm $secondTerm;

    protected function setUp(): void
    {
        parent::setUp();

        // A second term so we have something to switch to
        $this->secondTerm = AcademicTerm::create([
            'academic_year_id' => $this->year->id,
            'term_number'      => 2,
            'term_name'        => 'Second Term',
            'start_date'       => now()->addDays(60)->toDateString(),
            'end_date'         => now()->addDays(150)->toDateString(),
            'is_current'       => false,
        ]);
    }

    // ── Page ─────────────────────────────────────────────────────────────────

    public function test_calendar_settings_page_returns_200(): void
    {
        $this->asAdmin()->get('/settings/calendar')
            ->assertOk()
            ->assertViewIs('admin.settings.calendar')
            ->assertViewHas('years')
            ->assertViewHas('currentTermId');
    }

    public function test_calendar_page_shows_all_terms(): void
    {
        $this->asAdmin()->get('/settings/calendar')
            ->assertOk()
            ->assertSee('First Term')
            ->assertSee('Second Term');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_set_current_term(): void
    {
        $this->asAdmin()->put('/settings/calendar', [
            'current_term_id' => $this->secondTerm->id,
        ])->assertRedirect()
          ->assertSessionHas('success');

        $this->assertDatabaseHas('tenants', [
            'id'              => $this->tenant->id,
            'current_term_id' => $this->secondTerm->id,
        ]);
    }

    public function test_admin_can_clear_current_term_to_follow_global(): void
    {
        // First set a term
        $this->tenant->update(['current_term_id' => $this->secondTerm->id]);

        // Then clear it
        $this->asAdmin()->put('/settings/calendar', [
            'current_term_id' => '',
        ])->assertRedirect()
          ->assertSessionHas('success');

        $this->assertDatabaseHas('tenants', [
            'id'              => $this->tenant->id,
            'current_term_id' => null,
        ]);
    }

    public function test_update_rejects_nonexistent_term(): void
    {
        $this->asAdmin()->put('/settings/calendar', [
            'current_term_id' => 99999,
        ])->assertSessionHasErrors('current_term_id');
    }

    // ── Tenant-aware AcademicTerm::current() ──────────────────────────────────

    public function test_academic_term_current_returns_tenant_term_when_set(): void
    {
        // Bind the tenant to the container (mimics ResolveTenantMiddleware)
        $this->tenant->update(['current_term_id' => $this->secondTerm->id]);
        app()->instance('currentTenant', $this->tenant->fresh());

        $resolved = AcademicTerm::current();

        $this->assertNotNull($resolved);
        $this->assertEquals($this->secondTerm->id, $resolved->id);
    }

    public function test_academic_term_current_falls_back_to_global_when_no_tenant_term(): void
    {
        // Tenant has no current_term_id — should fall back to is_current flag
        $this->tenant->update(['current_term_id' => null]);
        app()->instance('currentTenant', $this->tenant->fresh());

        $resolved = AcademicTerm::current();

        // The global term (is_current = true) was created in AdminTestCase setUp()
        $this->assertNotNull($resolved);
        $this->assertEquals($this->term->id, $resolved->id);
    }

    public function test_academic_year_current_follows_tenant_term(): void
    {
        // Give the second term its own academic year to make this unambiguous
        $newYear = AcademicYear::create([
            'year_label' => 'Cal-Test-Year',
            'is_current' => false,
        ]);
        $termInNewYear = AcademicTerm::create([
            'academic_year_id' => $newYear->id,
            'term_number'      => 1,
            'term_name'        => 'Term 1',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(90)->toDateString(),
            'is_current'       => false,
        ]);

        $this->tenant->update(['current_term_id' => $termInNewYear->id]);
        app()->instance('currentTenant', $this->tenant->fresh());

        $resolved = AcademicYear::current();

        $this->assertNotNull($resolved);
        $this->assertEquals($newYear->id, $resolved->id);
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_guest_redirected_from_calendar_settings(): void
    {
        $this->asGuest()->get('/settings/calendar')->assertRedirect();
    }
}
