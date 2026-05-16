<?php

namespace Tests\Feature\Admin;

use App\Services\GradeCalculator;

/**
 * Tests for SettingsController (school profile, grading, website, calendar, account).
 */
class SettingsControllerTest extends AdminTestCase
{
    // ── School profile ────────────────────────────────────────────────────────

    public function test_school_settings_page_is_accessible(): void
    {
        $this->asAdmin()->get('/settings')->assertOk();
    }

    public function test_update_school_profile_persists_changes(): void
    {
        $this->asAdmin()->put('/settings', [
            'name'          => 'Updated School Name',
            'address'       => '1 New Road, Accra',
            'phone'         => '+233201112222',
            'contact_phone' => '+233201112222',
            'contact_email' => 'newcontact@testschool.edu.gh',
            'primary_color' => '#FF5500',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenants', [
            'id'            => $this->tenant->id,
            'name'          => 'Updated School Name',
            'primary_color' => '#FF5500',
        ]);
    }

    public function test_guest_is_redirected_from_school_settings(): void
    {
        $this->asGuest()->get('/settings')->assertRedirect();
    }

    // ── Grading settings ──────────────────────────────────────────────────────

    public function test_grading_settings_page_is_accessible(): void
    {
        $this->asAdmin()->get('/settings/grading')->assertOk();
    }

    public function test_update_grading_settings_persists_changes(): void
    {
        $scale = GradeCalculator::scale();

        $this->asAdmin()->put('/settings/grading', [
            'ca_max'   => 40,
            'exam_max' => 60,
            'scale'    => array_map(fn ($b) => [
                'min'    => $b['min'],
                'max'    => $b['max'],
                'grade'  => $b['grade'],
                'remark' => $b['remark'],
                'points' => $b['points'],
            ], $scale),
        ])->assertRedirect()->assertSessionHas('success');

        $stored = $this->tenant->fresh()->grading_settings;
        $this->assertEquals(40, $stored['ca_max']);
        $this->assertEquals(60, $stored['exam_max']);
    }

    public function test_grading_rejects_ca_and_exam_not_summing_to_100(): void
    {
        $scale = GradeCalculator::scale();

        $this->asAdmin()->put('/settings/grading', [
            'ca_max'   => 40,
            'exam_max' => 50,   // 40 + 50 = 90, not 100
            'scale'    => array_map(fn ($b) => [
                'min'    => $b['min'],
                'max'    => $b['max'],
                'grade'  => $b['grade'],
                'remark' => $b['remark'],
                'points' => $b['points'],
            ], $scale),
        ])->assertSessionHasErrors('ca_max');
    }

    public function test_reset_grading_clears_tenant_override(): void
    {
        $this->tenant->update(['grading_settings' => ['ca_max' => 40, 'exam_max' => 60, 'scale' => []]]);

        $this->asAdmin()->delete('/settings/grading/reset')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull($this->tenant->fresh()->grading_settings);
    }

    // ── Website content ───────────────────────────────────────────────────────

    public function test_website_content_settings_page_is_accessible(): void
    {
        $this->asAdmin()->get('/settings/website')->assertOk();
    }

    public function test_update_website_content_persists_tagline(): void
    {
        $this->asAdmin()->put('/settings/website', [
            'hero_tagline'   => 'Excellence in Education',
            'admissions_open'=> '1',
        ])->assertRedirect()->assertSessionHas('success');

        $content = $this->tenant->fresh()->website_content;
        $this->assertEquals('Excellence in Education', $content['hero_tagline']);
        $this->assertTrue($content['admissions_open']);
    }

    // ── Academic calendar ─────────────────────────────────────────────────────

    public function test_calendar_settings_page_is_accessible(): void
    {
        $this->asAdmin()->get('/settings/calendar')->assertOk();
    }

    public function test_update_calendar_sets_current_term(): void
    {
        $this->asAdmin()->put('/settings/calendar', [
            'current_term_id' => $this->term->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals($this->term->id, $this->tenant->fresh()->current_term_id);
    }

    public function test_update_calendar_with_null_resets_to_global_term(): void
    {
        $this->tenant->update(['current_term_id' => $this->term->id]);

        $this->asAdmin()->put('/settings/calendar', [
            'current_term_id' => '',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertNull($this->tenant->fresh()->current_term_id);
    }

    // ── Account (password change) ─────────────────────────────────────────────

    public function test_account_settings_page_is_accessible(): void
    {
        $this->asAdmin()->get('/settings/account')->assertOk();
    }

    public function test_update_account_password_with_correct_current_password(): void
    {
        $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'password',   // factory default
            'password'              => 'NewSecure@99',
            'password_confirmation' => 'NewSecure@99',
        ])->assertRedirect()->assertSessionHas('success');
    }

    public function test_update_account_rejects_wrong_current_password(): void
    {
        $this->asAdmin()->put('/settings/account', [
            'current_password'      => 'wrong-password',
            'password'              => 'NewSecure@99',
            'password_confirmation' => 'NewSecure@99',
        ])->assertSessionHasErrors('current_password');
    }
}
