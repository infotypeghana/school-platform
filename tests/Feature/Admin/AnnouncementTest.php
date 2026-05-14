<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\SchoolClass;

class AnnouncementTest extends AdminTestCase
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $this->asAdmin()->get('/announcements')->assertOk()->assertViewIs('admin.announcements.index');
    }

    public function test_index_shows_existing_announcements(): void
    {
        Announcement::create([
            'tenant_id'    => $this->tenant->id,
            'created_by'   => $this->user->id,
            'title'        => 'Welcome Back',
            'body'         => 'School resumes Monday.',
            'audience'     => 'all',
            'published_at' => now()->subHour(),
        ]);

        $this->asAdmin()->get('/announcements')->assertSee('Welcome Back');
    }

    public function test_guest_is_redirected(): void
    {
        $this->asGuest()->get('/announcements')->assertRedirect();
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function test_create_form_returns_200(): void
    {
        $this->asAdmin()->get('/announcements/create')->assertOk()->assertViewIs('admin.announcements.form');
    }

    public function test_store_creates_announcement(): void
    {
        $this->asAdmin()->post('/announcements', [
            'title'        => 'Sports Day',
            'body'         => 'Join us Friday for sports day.',
            'audience'     => 'all',
            'published_at' => now()->format('Y-m-d\TH:i'),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('announcements', ['title' => 'Sports Day']);
    }

    public function test_store_pinned_announcement(): void
    {
        $this->asAdmin()->post('/announcements', [
            'title'        => 'Pinned Notice',
            'body'         => 'Important notice.',
            'audience'     => 'parents',
            'is_pinned'    => '1',
            'published_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $this->assertDatabaseHas('announcements', ['title' => 'Pinned Notice', 'is_pinned' => 1]);
    }

    public function test_store_class_audience_requires_class_id(): void
    {
        $response = $this->asAdmin()->post('/announcements', [
            'title'    => 'Class Notice',
            'body'     => 'For class only.',
            'audience' => 'class',
            // school_class_id intentionally omitted
        ]);

        $response->assertSessionHasErrors('school_class_id');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->asAdmin()->post('/announcements', [])
            ->assertSessionHasErrors(['title', 'body', 'audience']);
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_returns_200(): void
    {
        $a = Announcement::create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
            'title'      => 'Edit Me',
            'body'       => 'Body here.',
            'audience'   => 'all',
        ]);

        $this->asAdmin()->get("/announcements/{$a->id}/edit")
            ->assertOk()->assertViewIs('admin.announcements.form');
    }

    public function test_update_changes_title(): void
    {
        $a = Announcement::create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
            'title'      => 'Old Title',
            'body'       => 'Body.',
            'audience'   => 'all',
        ]);

        $this->asAdmin()->put("/announcements/{$a->id}", [
            'title'    => 'New Title',
            'body'     => 'Body.',
            'audience' => 'teachers',
        ]);

        $this->assertDatabaseHas('announcements', ['id' => $a->id, 'title' => 'New Title', 'audience' => 'teachers']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_destroy_removes_announcement(): void
    {
        $a = Announcement::create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
            'title'      => 'Delete Me',
            'body'       => 'Body.',
            'audience'   => 'all',
        ]);

        $this->asAdmin()->delete("/announcements/{$a->id}")->assertRedirect();
        $this->assertDatabaseMissing('announcements', ['id' => $a->id]);
    }

    // ── Model scopes ──────────────────────────────────────────────────────────

    public function test_active_scope_excludes_expired(): void
    {
        Announcement::create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
            'title'      => 'Expired',
            'body'       => 'Body.',
            'audience'   => 'all',
            'expires_at' => now()->subDay(),
        ]);

        $this->assertSame(0, Announcement::active()->where('title', 'Expired')->count());
    }

    public function test_active_scope_includes_non_expired(): void
    {
        Announcement::create([
            'tenant_id'  => $this->tenant->id,
            'created_by' => $this->user->id,
            'title'      => 'Still Active',
            'body'       => 'Body.',
            'audience'   => 'all',
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(1, Announcement::active()->where('title', 'Still Active')->count());
    }
}
