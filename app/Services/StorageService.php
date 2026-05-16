<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Central object-storage abstraction layer.
 *
 * Provides:
 *  - Tenant-namespaced storage paths (prevents path traversal cross-tenant)
 *  - Signed URL generation for private files (time-limited, tamper-proof)
 *  - Unified upload/delete across local, S3, R2, and B2 compatible disks
 *  - Access control enforcement (tenant files are inaccessible to other tenants)
 *
 * Usage:
 *   $service = app(StorageService::class);
 *   $path    = $service->upload($request->file('logo'), 'logos');
 *   $url     = $service->signedUrl($path, 60);  // 60 minutes
 *   $service->delete($path);
 */
class StorageService
{
    /** Disk used for public media (logos, images). */
    private string $mediaDisk;

    /** Disk used for private files (exports, reports, confidential docs). */
    private string $privateDisk;

    public function __construct()
    {
        $this->mediaDisk   = config('filesystems.media_disk',   'public');
        $this->privateDisk = config('filesystems.private_disk', 'local');
    }

    // ── Upload ────────────────────────────────────────────────────────────────

    /**
     * Upload a file to the media disk, namespaced under the current tenant.
     * Returns the relative path stored in the database.
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @param  string  $directory  e.g. 'logos', 'student-images'
     * @return string  e.g. 'tenants/42/logos/abc123.jpg'
     */
    public function upload(\Illuminate\Http\UploadedFile $file, string $directory = 'files', ?Tenant $tenant = null): string
    {
        $tenant = $tenant ?? app('currentTenant');
        $prefix = $tenant ? "tenants/{$tenant->id}" : 'shared';

        return $file->store("{$prefix}/{$directory}", $this->mediaDisk);
    }

    /**
     * Upload a private file (reports, exports). Stored on private disk, never publicly accessible.
     */
    public function uploadPrivate(\Illuminate\Http\UploadedFile $file, string $directory = 'files', ?Tenant $tenant = null): string
    {
        $tenant = $tenant ?? app('currentTenant');
        $prefix = $tenant ? "tenants/{$tenant->id}" : 'shared';

        return $file->store("{$prefix}/{$directory}", $this->privateDisk);
    }

    /**
     * Store raw content (e.g. generated PDF) on the private disk.
     */
    public function storePrivate(string $path, string $content, ?Tenant $tenant = null): string
    {
        $tenant = $tenant ?? app('currentTenant');
        $prefix = $tenant ? "tenants/{$tenant->id}" : 'shared';
        $fullPath = "{$prefix}/{$path}";

        Storage::disk($this->privateDisk)->put($fullPath, $content);

        return $fullPath;
    }

    // ── URL generation ────────────────────────────────────────────────────────

    /**
     * Get a public URL for a media file.
     * Works with local (public) and S3 disks.
     */
    public function url(string $path): string
    {
        return Storage::disk($this->mediaDisk)->url($path);
    }

    /**
     * Generate a signed (time-limited, HMAC-verified) URL for a private file.
     * Uses Laravel's signed routes — works regardless of disk type.
     *
     * For S3/R2/B2: uses native S3 presigned URLs (more efficient).
     * For local:    uses Laravel signed route `/storage/signed/{path}`.
     *
     * @param  string  $path     Relative path as stored in the database
     * @param  int     $minutes  Expiry in minutes (default 60)
     */
    public function signedUrl(string $path, int $minutes = 60): string
    {
        // S3-compatible disks support native presigned URLs
        if ($this->isS3Disk($this->privateDisk)) {
            return Storage::disk($this->privateDisk)->temporaryUrl(
                $path,
                now()->addMinutes($minutes),
            );
        }

        // Local disk: use signed route
        return URL::temporarySignedRoute(
            'storage.signed',
            now()->addMinutes($minutes),
            ['path' => $path],
        );
    }

    /**
     * Generate a signed URL for a media file (public disk).
     * Used when temporary access is needed for a normally-public file.
     */
    public function signedMediaUrl(string $path, int $minutes = 60): string
    {
        if ($this->isS3Disk($this->mediaDisk)) {
            return Storage::disk($this->mediaDisk)->temporaryUrl(
                $path,
                now()->addMinutes($minutes),
            );
        }

        return URL::temporarySignedRoute(
            'storage.signed',
            now()->addMinutes($minutes),
            ['path' => $path],
        );
    }

    // ── Tenant access enforcement ─────────────────────────────────────────────

    /**
     * Verify a storage path belongs to the current tenant.
     * Prevents tenants from crafting paths to other tenants' files.
     * Throws 403 if the path is not owned by the current tenant.
     *
     * @param  string  $path  Path as stored in DB
     */
    public function assertOwnership(string $path, ?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? app('currentTenant');

        if (! $tenant) {
            return; // super admin — no restriction
        }

        $expectedPrefix = "tenants/{$tenant->id}/";

        abort_unless(
            str_starts_with($path, $expectedPrefix),
            403,
            'You do not have access to this file.'
        );
    }

    /**
     * Check (non-aborting) whether a path belongs to a tenant.
     */
    public function ownedBy(string $path, Tenant $tenant): bool
    {
        return str_starts_with($path, "tenants/{$tenant->id}/");
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function delete(string $path, bool $isPrivate = false): bool
    {
        $disk = $isPrivate ? $this->privateDisk : $this->mediaDisk;
        return Storage::disk($disk)->delete($path);
    }

    // ── Usage tracking ────────────────────────────────────────────────────────

    /**
     * Return approximate disk usage (MB) for a tenant across both disks.
     */
    public function tenantUsageMb(Tenant $tenant): float
    {
        $prefix = "tenants/{$tenant->id}";
        $total  = 0;

        foreach ([$this->mediaDisk, $this->privateDisk] as $disk) {
            try {
                $files  = Storage::disk($disk)->allFiles($prefix);
                foreach ($files as $file) {
                    $total += Storage::disk($disk)->size($file);
                }
            } catch (\Throwable) {
                // disk may not support allFiles
            }
        }

        return round($total / 1_048_576, 2); // bytes → MB
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isS3Disk(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }
}
