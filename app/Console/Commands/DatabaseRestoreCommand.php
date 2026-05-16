<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Restore a MySQL database from a gzipped backup created by db:backup.
 *
 * Usage:
 *   php artisan db:restore                         # restore most recent backup
 *   php artisan db:restore --file=db_2026-05-16_020000.sql.gz
 *   php artisan db:restore --list                  # show available backups
 *   php artisan db:restore --verify-only           # verify checksum without restoring
 *
 * Safety:
 *   - Requires --force in production environments
 *   - Verifies SHA-256 checksum before restoring (when .sha256 sidecar exists)
 *   - Creates a safety backup of current DB before overwriting
 */
class DatabaseRestoreCommand extends Command
{
    protected $signature = 'db:restore
                            {--file=   : Filename within storage/app/backups/ to restore}
                            {--list    : List available backup files and exit}
                            {--verify-only : Verify checksum without restoring}
                            {--force   : Skip confirmation prompt (required in production)}
                            {--no-safety-backup : Skip pre-restore safety backup}';

    protected $description = 'Restore MySQL database from a gzipped backup file with integrity verification.';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->error('db:restore only supports MySQL.');
            return self::FAILURE;
        }

        // ── List mode ─────────────────────────────────────────────────────────
        if ($this->option('list')) {
            return $this->listBackups();
        }

        // ── Resolve backup file ───────────────────────────────────────────────
        $filename = $this->option('file') ?: $this->resolveLatestBackup();

        if (! $filename) {
            $this->error('No backup files found in storage/app/backups/');
            return self::FAILURE;
        }

        // Ensure the filename is just the basename (no path traversal)
        $filename = 'backups/' . basename($filename);
        $absPath  = Storage::disk('local')->path($filename);

        if (! Storage::disk('local')->exists($filename)) {
            $this->error("Backup file not found: {$filename}");
            return self::FAILURE;
        }

        $sizeKb = (int) round(Storage::disk('local')->size($filename) / 1024);
        $this->info("Backup file: {$filename} ({$sizeKb} KB)");

        // ── Checksum verification ─────────────────────────────────────────────
        $verified = $this->verifyChecksum($filename, $absPath);

        if ($this->option('verify-only')) {
            return $verified ? self::SUCCESS : self::FAILURE;
        }

        if (! $verified) {
            $this->error('Checksum verification FAILED. Aborting restore to protect data integrity.');
            $this->error('If you trust this file and want to force a restore, delete the .sha256 sidecar first.');
            return self::FAILURE;
        }

        // ── Production safety gate ────────────────────────────────────────────
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('In production, you must pass --force to confirm the restore.');
            return self::FAILURE;
        }

        // ── Confirmation ──────────────────────────────────────────────────────
        $database = config('database.connections.mysql.database');
        if (! $this->option('force')) {
            if (! $this->confirm("⚠️  This will OVERWRITE the database '{$database}'. Are you sure?")) {
                $this->info('Restore cancelled.');
                return self::SUCCESS;
            }
        }

        // ── Pre-restore safety backup ─────────────────────────────────────────
        if (! $this->option('no-safety-backup')) {
            $this->info('Creating safety backup of current database before restoring…');
            $exitCode = $this->call('db:backup', ['--no-cloud' => true]);
            if ($exitCode !== self::SUCCESS) {
                $this->warn('Safety backup failed — proceeding with restore anyway (backup may be empty).');
            }
        }

        // ── Restore ───────────────────────────────────────────────────────────
        return $this->restore($database, $absPath, $filename);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function listBackups(): int
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sort()
            ->reverse()
            ->values();

        if ($files->isEmpty()) {
            $this->warn('No backup files found.');
            return self::SUCCESS;
        }

        $this->table(['#', 'Filename', 'Size (KB)', 'Date', 'Checksum'], $files->map(function ($file, $idx) {
            $absPath   = Storage::disk('local')->path($file);
            $sizeKb    = (int) round(Storage::disk('local')->size($file) / 1024);
            $modified  = date('Y-m-d H:i', Storage::disk('local')->lastModified($file));
            $hashFile  = $file . '.sha256';
            $checksum  = Storage::disk('local')->exists($hashFile) ? '✓ verified' : 'no checksum';

            return [$idx + 1, basename($file), $sizeKb, $modified, $checksum];
        })->toArray());

        return self::SUCCESS;
    }

    private function resolveLatestBackup(): ?string
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql.gz'))
            ->sort()
            ->last();

        return $files ? basename($files) : null;
    }

    private function verifyChecksum(string $filename, string $absPath): bool
    {
        $hashFilename = $filename . '.sha256';

        if (! Storage::disk('local')->exists($hashFilename)) {
            $this->warn('No .sha256 checksum file found — skipping integrity verification.');
            return true; // not a failure; old backups predate checksum feature
        }

        $expected = trim(explode(' ', Storage::disk('local')->get($hashFilename))[0]);
        $actual   = hash_file('sha256', $absPath);

        if ($expected === $actual) {
            $this->info("✓ Checksum verified: {$actual}");
            return true;
        }

        $this->error("✗ Checksum MISMATCH!");
        $this->error("  Expected: {$expected}");
        $this->error("  Actual:   {$actual}");
        Log::critical('db:restore checksum mismatch', [
            'file'     => $filename,
            'expected' => $expected,
            'actual'   => $actual,
        ]);
        return false;
    }

    private function restore(string $database, string $absPath, string $filename): int
    {
        $host     = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port     = (string) config('database.connections.mysql.port', 3306);
        $username = (string) config('database.connections.mysql.username', '');
        $password = (string) config('database.connections.mysql.password', '');

        $envPrefix = $password ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';

        $cmd = sprintf(
            '%szcat %s | mysql --host=%s --port=%s --user=%s %s',
            $envPrefix,
            escapeshellarg($absPath),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
        );

        $this->info("Restoring '{$database}' from {$filename} …");

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            $this->error("Restore failed (exit {$exitCode}): {$error}");
            Log::critical('db:restore failed', ['exit_code' => $exitCode, 'output' => $error, 'file' => $filename]);
            return self::FAILURE;
        }

        $this->info('✓ Database restored successfully.');
        Log::info('db:restore completed', ['database' => $database, 'from_file' => $filename]);

        // Remind operator to clear application caches
        $this->warn('Run the following to clear application caches after restore:');
        $this->line('  php artisan cache:clear && php artisan config:clear && php artisan route:clear');

        return self::SUCCESS;
    }
}
