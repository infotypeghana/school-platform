<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Daily MySQL database dump — runs at 02:00 WAT via the scheduler.
 *
 * Usage:
 *   php artisan db:backup                     # run immediately
 *   php artisan db:backup --dry-run           # show what would be done
 *   php artisan db:backup --keep=14           # keep 14 days instead of default 7
 *   php artisan db:backup --no-cloud          # skip S3 upload even if configured
 *
 * Output:
 *   Local:  storage/app/backups/db_<date>_<time>.sql.gz
 *   Cloud:  s3://PRIVATE_BUCKET/backups/db_<date>_<time>.sql.gz (if configured)
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--dry-run  : Show the dump command without executing it}
                            {--keep=7   : Number of days to retain local backup files}
                            {--no-cloud : Skip cloud (S3) upload}';

    protected $description = 'Dump MySQL database to gzipped SQL; optionally sync to S3.';

    public function handle(): int
    {
        if (config('database.default') !== 'mysql') {
            $this->warn('db:backup only supports MySQL. Current driver: ' . config('database.default'));
            return self::FAILURE;
        }

        // ── Build file path ───────────────────────────────────────────────────
        $timestamp = now()->format('Y-m-d_His');
        $filename  = "backups/db_{$timestamp}.sql.gz";
        $absPath   = Storage::disk('local')->path($filename);

        Storage::disk('local')->makeDirectory('backups');

        // ── Build mysqldump command ────────────────────────────────────────────
        $host     = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port     = (string) config('database.connections.mysql.port', 3306);
        $database = (string) config('database.connections.mysql.database', '');
        $username = (string) config('database.connections.mysql.username', '');
        $password = (string) config('database.connections.mysql.password', '');

        // Use MYSQL_PWD env var so the password never appears in `ps aux` output
        $envPrefix = $password ? 'MYSQL_PWD=' . escapeshellarg($password) . ' ' : '';

        $cmd = sprintf(
            '%smysqldump --host=%s --port=%s --user=%s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            $envPrefix,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($absPath),
        );

        if ($this->option('dry-run')) {
            $maskedCmd = str_replace($envPrefix, 'MYSQL_PWD=*** ', $cmd);
            $this->line($maskedCmd);
            return self::SUCCESS;
        }

        // ── Execute dump ──────────────────────────────────────────────────────
        $this->info("Dumping database '{$database}' to {$filename} …");

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            $this->error("mysqldump failed (exit {$exitCode}): {$error}");
            Log::error('db:backup failed', ['exit_code' => $exitCode, 'output' => $error]);
            $this->notifyFailure("mysqldump exited {$exitCode}: {$error}");
            return self::FAILURE;
        }

        $sizeKb = (int) round(Storage::disk('local')->size($filename) / 1024);
        $this->info("Backup written: {$filename} ({$sizeKb} KB)");
        Log::info('db:backup completed', ['file' => $filename, 'size_kb' => $sizeKb]);

        // ── Verify backup is not suspiciously small ───────────────────────────
        if ($sizeKb < 1) {
            $this->error('Backup file is suspiciously small (< 1 KB) — possible dump failure.');
            $this->notifyFailure('Backup file appears empty. Check mysqldump output.');
            return self::FAILURE;
        }

        // ── Write SHA-256 checksum sidecar ────────────────────────────────────
        $hash         = hash_file('sha256', $absPath);
        $hashFilename = $filename . '.sha256';
        $hashAbsPath  = Storage::disk('local')->path($hashFilename);
        file_put_contents($hashAbsPath, $hash . '  ' . basename($absPath) . PHP_EOL);
        $this->info("Checksum written: {$hashFilename}");
        Log::info('db:backup checksum', ['file' => $filename, 'sha256' => $hash]);

        // ── Upload to cloud (S3) ──────────────────────────────────────────────
        if (! $this->option('no-cloud') && $this->cloudConfigured()) {
            $this->uploadToCloud($filename, $absPath, $hashFilename, $hashAbsPath);
        }

        // ── Rotate old local backups ──────────────────────────────────────────
        $this->rotateOldBackups((int) $this->option('keep'));

        return self::SUCCESS;
    }

    // ── Cloud upload ──────────────────────────────────────────────────────────

    private function cloudConfigured(): bool
    {
        return (bool) config('filesystems.disks.s3.bucket')
            && (bool) config('filesystems.disks.s3.key');
    }

    private function uploadToCloud(string $relativePath, string $absPath, string $hashRelativePath = '', string $hashAbsPath = ''): void
    {
        $this->info('Uploading backup to cloud storage…');

        try {
            $cloudPath = 'backups/' . basename($relativePath);

            Storage::disk('s3')->put($cloudPath, fopen($absPath, 'rb'), 'private');

            // Upload sidecar checksum file
            if ($hashAbsPath && file_exists($hashAbsPath)) {
                Storage::disk('s3')->put('backups/' . basename($hashRelativePath), fopen($hashAbsPath, 'rb'), 'private');
            }

            $this->info("Backup synced to cloud: {$cloudPath}");
            Log::info('db:backup synced to cloud', ['cloud_path' => $cloudPath]);

            $this->rotateCloudBackups();
        } catch (\Throwable $e) {
            $this->warn("Cloud upload failed (local backup still available): {$e->getMessage()}");
            Log::error('db:backup cloud upload failed', ['error' => $e->getMessage()]);
        }
    }

    private function rotateCloudBackups(): void
    {
        try {
            $files   = Storage::disk('s3')->files('backups');
            $cutoff  = now()->subDays(30)->timestamp;
            $deleted = 0;

            foreach ($files as $file) {
                if (! str_ends_with($file, '.sql.gz')) {
                    continue;
                }
                if (Storage::disk('s3')->lastModified($file) < $cutoff) {
                    Storage::disk('s3')->delete($file);
                    $deleted++;
                }
            }

            if ($deleted > 0) {
                $this->info("Rotated {$deleted} cloud backup(s) older than 30 days.");
            }
        } catch (\Throwable) {
            // Non-fatal — local rotation still runs
        }
    }

    // ── Local rotation ────────────────────────────────────────────────────────

    private function rotateOldBackups(int $keepDays): void
    {
        $files   = Storage::disk('local')->files('backups');
        $cutoff  = now()->subDays($keepDays)->timestamp;
        $deleted = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file, '.sql.gz')) {
                continue;
            }
            if (Storage::disk('local')->lastModified($file) < $cutoff) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Rotated {$deleted} local backup(s) older than {$keepDays} days.");
        }
    }

    // ── Failure notification ──────────────────────────────────────────────────

    private function notifyFailure(string $message): void
    {
        try {
            $email = config('app.admin_email') ?: env('SUPER_ADMIN_EMAIL');
            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new \App\Notifications\BackupFailedNotification($message));
            }
        } catch (\Throwable) {
            // Non-fatal — backup failure is already logged
        }
    }
}
