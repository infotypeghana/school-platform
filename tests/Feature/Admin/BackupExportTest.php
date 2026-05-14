<?php

namespace Tests\Feature\Admin;

use App\Jobs\ExportSchoolDataJob;
use App\Models\SchoolClass;
use App\Models\SchoolExport;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Backup & full school data export.
 *
 * Tests: trigger export, prevent duplicate, download, delete, expired access.
 * The actual ZIP generation is tested separately via the job unit test.
 */
class BackupExportTest extends AdminTestCase
{
    // ── Index page ────────────────────────────────────────────────────────────

    public function test_backup_index_is_accessible(): void
    {
        $this->asAdmin()->get('/backup')->assertOk()->assertViewIs('admin.backup.index');
    }

    public function test_guest_cannot_access_backup(): void
    {
        $this->asGuest()->get('/backup')->assertRedirect();
    }

    // ── Store (trigger export) ────────────────────────────────────────────────

    public function test_store_dispatches_job_and_creates_export_record(): void
    {
        Queue::fake();

        $response = $this->asAdmin()->post('/backup');

        $response->assertRedirect();

        Queue::assertPushed(ExportSchoolDataJob::class);

        $this->assertDatabaseHas('school_exports', [
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_PENDING,
        ]);
    }

    public function test_cannot_trigger_duplicate_export_while_in_flight(): void
    {
        Queue::fake();

        SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_PROCESSING,
        ]);

        $response = $this->asAdmin()->post('/backup');

        $response->assertRedirect();
        $response->assertSessionHas('info');

        Queue::assertNothingPushed();
    }

    // ── Download ──────────────────────────────────────────────────────────────

    public function test_download_returns_file_when_ready(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/test.zip', 'fake zip content');

        $export = SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_READY,
            'file_path'    => 'exports/test.zip',
            'ready_at'     => now(),
            'expires_at'   => now()->addHours(24),
        ]);

        $response = $this->asAdmin()->get("/backup/{$export->id}/download");

        $response->assertOk();
        $response->assertDownload('test.zip');
    }

    public function test_download_of_pending_export_redirects_with_error(): void
    {
        $export = SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_PENDING,
        ]);

        $this->asAdmin()->get("/backup/{$export->id}/download")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_download_of_expired_export_redirects_with_error(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/old.zip', 'zip');

        $export = SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_READY,
            'file_path'    => 'exports/old.zip',
            'ready_at'     => now()->subDays(2),
            'expires_at'   => now()->subDay(), // already expired
        ]);

        $this->asAdmin()->get("/backup/{$export->id}/download")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_cannot_download_other_tenants_export(): void
    {
        // Create a separate real tenant + export
        $otherTenant = Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'other-backup-school',
            'name'   => 'Other Backup School',
            'email'  => 'info@other-backup.edu.gh',
            'status' => 'active',
        ]);

        $export = SchoolExport::create([
            'tenant_id'    => $otherTenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_READY,
            'file_path'    => 'exports/ghost.zip',
            'ready_at'     => now(),
            'expires_at'   => now()->addHours(24),
        ]);

        // The BackupController scopes by currentTenant, so this returns 404
        $this->asAdmin()->get("/backup/{$export->id}/download")
            ->assertNotFound();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_export_record(): void
    {
        $export = SchoolExport::create([
            'tenant_id'    => $this->tenant->id,
            'requested_by' => $this->user->id,
            'status'       => SchoolExport::STATUS_FAILED,
        ]);

        $this->asAdmin()->delete("/backup/{$export->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('school_exports', ['id' => $export->id]);
    }
}
