<?php

namespace Tests\Feature\Website;

use App\Models\Subscription;

class WebsiteTest extends WebsiteTestCase
{
    // ── Static pages ──────────────────────────────────────────────────────────

    public function test_home_returns_200(): void
    {
        $this->get('/')->assertOk()->assertViewIs('website.home');
    }

    public function test_about_returns_200(): void
    {
        $this->get('/about')->assertOk()->assertViewIs('website.about');
    }

    public function test_academics_returns_200(): void
    {
        $this->get('/academics')->assertOk()->assertViewIs('website.academics');
    }

    public function test_news_returns_200(): void
    {
        $this->get('/news')->assertOk()->assertViewIs('website.news');
    }

    public function test_gallery_returns_200(): void
    {
        $this->get('/gallery')->assertOk()->assertViewIs('website.gallery');
    }

    public function test_contact_returns_200(): void
    {
        $this->get('/contact')->assertOk()->assertViewIs('website.contact');
    }

    // ── Admissions page ───────────────────────────────────────────────────────

    public function test_admissions_page_returns_200(): void
    {
        $this->get('/admissions')->assertOk()->assertViewIs('website.admissions');
    }

    public function test_admissions_page_passes_current_term_to_view(): void
    {
        $response = $this->get('/admissions');

        $response->assertViewHas('term');
    }

    // ── Admission application form ────────────────────────────────────────────

    public function test_apply_admission_creates_record_and_redirects_back(): void
    {
        $response = $this->post('/admissions', [
            'first_name'     => 'Kofi',
            'last_name'      => 'Asante',
            'date_of_birth'  => '2012-03-15',
            'gender'         => 'male',
            'desired_class'  => 'Basic 1',
            'guardian_name'  => 'Kweku Asante',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('applied');

        $this->assertDatabaseHas('admissions', [
            'tenant_id'  => $this->tenant->id,
            'first_name' => 'Kofi',
            'last_name'  => 'Asante',
            'status'     => 'pending',
        ]);
    }

    public function test_apply_requires_first_name(): void
    {
        $response = $this->post('/admissions', [
            'last_name'      => 'Asante',
            'date_of_birth'  => '2012-03-15',
            'gender'         => 'male',
            'desired_class'  => 'Basic 1',
            'guardian_name'  => 'Kweku Asante',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertSessionHasErrors('first_name');
    }

    public function test_apply_requires_last_name(): void
    {
        $response = $this->post('/admissions', [
            'first_name'     => 'Kofi',
            'date_of_birth'  => '2012-03-15',
            'gender'         => 'male',
            'desired_class'  => 'Basic 1',
            'guardian_name'  => 'Kweku Asante',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertSessionHasErrors('last_name');
    }

    public function test_apply_requires_valid_gender(): void
    {
        $response = $this->post('/admissions', [
            'first_name'     => 'Kofi',
            'last_name'      => 'Asante',
            'date_of_birth'  => '2012-03-15',
            'gender'         => 'unknown',
            'desired_class'  => 'Basic 1',
            'guardian_name'  => 'Kweku Asante',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertSessionHasErrors('gender');
    }

    public function test_apply_requires_date_of_birth_in_past(): void
    {
        $response = $this->post('/admissions', [
            'first_name'     => 'Kofi',
            'last_name'      => 'Asante',
            'date_of_birth'  => now()->addYear()->toDateString(), // future date
            'gender'         => 'male',
            'desired_class'  => 'Basic 1',
            'guardian_name'  => 'Kweku Asante',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertSessionHasErrors('date_of_birth');
    }

    public function test_apply_requires_guardian_name(): void
    {
        $response = $this->post('/admissions', [
            'first_name'     => 'Kofi',
            'last_name'      => 'Asante',
            'date_of_birth'  => '2012-03-15',
            'gender'         => 'male',
            'desired_class'  => 'Basic 1',
            'guardian_phone' => '0244123456',
        ]);

        $response->assertSessionHasErrors('guardian_name');
    }

    public function test_apply_requires_guardian_phone(): void
    {
        $response = $this->post('/admissions', [
            'first_name'    => 'Kofi',
            'last_name'     => 'Asante',
            'date_of_birth' => '2012-03-15',
            'gender'        => 'male',
            'desired_class' => 'Basic 1',
            'guardian_name' => 'Kweku Asante',
        ]);

        $response->assertSessionHasErrors('guardian_phone');
    }

    // ── Contact form ──────────────────────────────────────────────────────────

    public function test_contact_form_redirects_back_on_success(): void
    {
        $response = $this->post('/contact', [
            'name'    => 'Jane Doe',
            'contact' => '0201234567',
            'subject' => 'School fees inquiry',
            'message' => 'I would like to know about your fee structure.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('contact_sent');
    }

    public function test_contact_form_requires_name(): void
    {
        $response = $this->post('/contact', [
            'contact' => '0201234567',
            'subject' => 'Test',
            'message' => 'Hello',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_contact_form_requires_message(): void
    {
        $response = $this->post('/contact', [
            'name'    => 'Jane Doe',
            'contact' => '0201234567',
            'subject' => 'Test',
        ]);

        $response->assertSessionHasErrors('message');
    }

    // ── Subscription-gated access ─────────────────────────────────────────────

    public function test_locked_subscription_serves_lock_screen(): void
    {
        $this->subscription->update(['status' => 'locked']);

        $response = $this->get('/');

        // WebsiteSubscriptionMiddleware returns 503 with the lock view so that
        // search engines and uptime monitors can detect the outage correctly.
        $response->assertStatus(503);
        $response->assertViewIs('website.lock');
    }

    public function test_active_subscription_shows_home_page(): void
    {
        // Subscription is active by default in setUp
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('website.home');
    }

    public function test_trial_subscription_shows_home_page(): void
    {
        $this->subscription->update(['status' => Subscription::STATUS_TRIAL]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('website.home');
    }
}
