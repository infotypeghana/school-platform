<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Daily MySQL database dump — runs at 02:00 WAT via the scheduler.
 *
 * Usage:
 *   php artisan db:backup                     # run immediately
 *   php artisan db:backup --dry-run           # show what would be done
 *   php artisan db:backup --keep=14           # keep 14 days instead of the default 7
 *
 * Output:
 *   Stored at: storage/app/backups/db_<date>_<time>.sql.gz
 *   Rotated:   files older than --keep days are deleted after a successful dump
 *
 * Limitations:
 *   - Only supports MySQL/MariaDB (uses mysqldump).
 *   - Requires mysqldump to be in $PATH on the server.
 *   - Use --ignore-platform-req on Windows dev machines (mysqldump may not exist).
 */
class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--dry-run : Show the dump command without executing it}
                            {--keep=7  : Number of days to retain backup files}';

    protected $description = 'Dump the MySQL database to a gzipped SQL file in storage/app/backups/';

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
        $host     = (string) config('database.connections.mysql.host', '');
        $port     = (string) config('database.connections.mysql.port', 3306);
        $database = (string) config('database.connections.mysql.database', '');
        $username = (string) config('database.connections.mysql.username', '');
        $password = (string) config('database.connections.mysql.password', '');

        // Pipe through gzip to keep file sizes manageable
        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s --single-transaction --quick --lock-tables=false %s | gzip > %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            $password ? '--password=' . escapeshellarg($password) : '',
            escapeshellarg($database),
            escapeshellarg($absPath),
        );

        if ($this->option('dry-run')) {
            // Mask password in dry-run output
            $masked = str_replace(
                $password ? '--password=' . escapeshellarg($password) : '',
                $password ? '--password=***' : '',
                $cmd
            );
            $this->line($masked);
            return self::SUCCESS;
        }

        // ── Execute dump ──────────────────────────────────────────────────────
        $this->info("Dumping database '{$database}' to {$filename} …");

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode("\n", $output);
            $this->error("mysqldump failed (exit {$exitCode}): {$error}");
            Log::error('db:backup failed', [
                'exit_code' => $exitCode,
                'output'    => $error,
            ]);
            return self::FAILURE;
        }

        $sizeKb = (int) round(Storage::disk('local')->size($filename) / 1024);
        $this->info("Backup written: {$filename} ({$sizeKb} KB)");

        Log::info('db:backup completed', [
            'file'    => $filename,
            'size_kb' => $sizeKb,
        ]);

        // ── Rotate old backups ────────────────────────────────────────────────
        $this->rotateOldBackups((int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function rotateOldBackups(int $keepDays): void
    {
        $files   = Storage::disk('local')->files('backups');
        $cutoff  = now()->subDays($keepDays);
        $deleted = 0;

        foreach ($files as $file) {
            if (! str_ends_with($file, '.sql.gz')) {
                continue;
            }

            $lastModified = Storage::disk('local')->lastModified($file);

            if ($lastModified < $cutoff->timestamp) {
                Storage::disk('local')->delete($file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Rotated {$deleted} backup(s) older than {$keepDays} days.");
        }
    }
}
