<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Automated Disaster Recovery Drill — monthly scheduled test.
 *
 * This command simulates a real database restore to a disposable scratch
 * schema (NOT the live database) and verifies the backup is valid and
 * restorable. It does NOT touch production data.
 *
 * Flow:
 *   1. List available backups — fail fast if none
 *   2. Select the most recent backup file
 *   3. Verify SHA-256 checksum integrity
 *   4. Decompress to a temp file
 *   5. Create a temporary scratch schema (db name + '_dr_drill')
 *   6. Restore the SQL dump into the scratch schema
 *   7. Verify at least one core table (tenants) exists in scratch
 *   8. Drop the scratch schema
 *   9. Log and report pass/fail
 *
 * Usage:
 *   php artisan dr:drill                # run full drill
 *   php artisan dr:drill --dry-run      # describe steps without executing
 *
 * Scheduled: 1st of every month, 03:00 WAT (automatically via routes/console.php)
 */
class DisasterRecoveryDrillCommand extends Command
{
    protected $signature = 'dr:drill
                            {--dry-run : Describe drill steps without executing any restore}';

    protected $description = 'Run monthly disaster recovery restore drill on a scratch schema';

    private string $drillDb = '';
    private string $tmpFile  = '';

    public function handle(): int
    {
        $startTime = microtime(true);
        $dryRun    = $this->option('dry-run');

        $this->info('══════════════════════════════════════════════════');
        $this->info('  SchoolMS DR Restore Drill — ' . now()->toDateTimeString());
        if ($dryRun) {
            $this->warn('  DRY RUN — no restore will be performed');
        }
        $this->info('══════════════════════════════════════════════════');

        $driver = DB::getDriverName();
        if ($driver !== 'mysql') {
            $this->warn("DR drill skipped — requires MySQL, running on {$driver}.");
            return self::SUCCESS;
        }

        try {
            // ── Step 1: Find most recent backup ───────────────────────────────
            $this->line('[1/7] Locating most recent backup…');
            $backupFile = $this->findLatestBackup();

            if (! $backupFile) {
                $this->fail('No backup files found in storage/app/backups/. Run db:backup first.');
                return self::FAILURE;
            }
            $this->info("      Found: {$backupFile}");

            if ($dryRun) {
                $this->dryRunReport($backupFile, $startTime);
                return self::SUCCESS;
            }

            // ── Step 2: Verify checksum ───────────────────────────────────────
            $this->line('[2/7] Verifying SHA-256 checksum…');
            if (! $this->verifyChecksum($backupFile)) {
                return self::FAILURE;
            }
            $this->info('      ✓ Checksum verified');

            // ── Step 3: Decompress to temp file ──────────────────────────────
            $this->line('[3/7] Decompressing backup…');
            $this->tmpFile = $this->decompress($backupFile);
            $this->info('      ✓ Decompressed to temp file');

            // ── Step 4: Create scratch schema ────────────────────────────────
            $this->drillDb = config('database.connections.mysql.database') . '_dr_drill';
            $this->line("[4/7] Creating scratch schema '{$this->drillDb}'…");
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$this->drillDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info('      ✓ Scratch schema created');

            // ── Step 5: Restore to scratch schema ────────────────────────────
            $this->line('[5/7] Restoring SQL dump into scratch schema…');
            $exitCode = $this->restoreToScratch($this->tmpFile);
            if ($exitCode !== 0) {
                $this->fail('Restore to scratch schema failed — see above output.');
                $this->cleanup();
                return self::FAILURE;
            }
            $this->info('      ✓ Restore completed');

            // ── Step 6: Integrity verification ───────────────────────────────
            $this->line('[6/7] Verifying restored data integrity…');
            if (! $this->verifyRestoredData()) {
                $this->fail('Integrity check failed — expected tables not found in scratch schema.');
                $this->cleanup();
                return self::FAILURE;
            }
            $this->info('      ✓ Core tables verified');

            // ── Step 7: Cleanup ───────────────────────────────────────────────
            $this->line('[7/7] Cleaning up scratch schema and temp files…');
            $this->cleanup();
            $this->info('      ✓ Cleaned up');

            // ── Success report ────────────────────────────────────────────────
            $durationSec = round(microtime(true) - $startTime, 1);
            $this->newLine();
            $this->info("✅ DR Drill PASSED — backup is valid and restorable ({$durationSec}s)");

            Log::info('[DR_DRILL] Disaster recovery drill PASSED', [
                'backup_file'  => $backupFile,
                'duration_sec' => $durationSec,
                'scratch_db'   => $this->drillDb,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('DR Drill FAILED: ' . $e->getMessage());

            Log::critical('[DR_DRILL] Disaster recovery drill FAILED', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            $this->cleanup();

            // Notify super admin of drill failure
            $adminEmail = config('mail.super_admin_email') ?? env('SUPER_ADMIN_EMAIL');
            if ($adminEmail) {
                \Illuminate\Support\Facades\Mail::raw(
                    "SchoolMS DR Drill FAILED at " . now()->toDateTimeString() . "\n\n" .
                    "Error: " . $e->getMessage() . "\n\n" .
                    "Action required: verify backup integrity and restore process manually.",
                    fn ($m) => $m->to($adminEmail)->subject('[ALERT] SchoolMS DR Drill Failed')
                );
            }

            return self::FAILURE;
        }
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function findLatestBackup(): ?string
    {
        $files = Storage::disk('local')->files('backups');
        $backups = array_filter($files, fn ($f) => str_ends_with($f, '.sql.gz'));

        if (empty($backups)) {
            return null;
        }

        // Sort descending by filename (timestamp-based names sort correctly)
        rsort($backups);
        return $backups[0];
    }

    private function verifyChecksum(string $backupPath): bool
    {
        $checksumPath = $backupPath . '.sha256';
        if (! Storage::disk('local')->exists($checksumPath)) {
            $this->warn('      ⚠ No checksum sidecar found — skipping verification (older backup)');
            return true;
        }

        $expectedHash = trim(Storage::disk('local')->get($checksumPath));
        $absPath      = Storage::disk('local')->path($backupPath);
        $actualHash   = hash_file('sha256', $absPath);

        if ($actualHash !== $expectedHash) {
            $this->error("      ✗ Checksum MISMATCH — backup may be corrupted!");
            $this->error("        Expected: {$expectedHash}");
            $this->error("        Actual:   {$actualHash}");
            Log::critical('[DR_DRILL] Backup checksum mismatch', [
                'file'     => $backupPath,
                'expected' => $expectedHash,
                'actual'   => $actualHash,
            ]);
            return false;
        }

        return true;
    }

    private function decompress(string $backupPath): string
    {
        $absPath = Storage::disk('local')->path($backupPath);
        $tmpFile = sys_get_temp_dir() . '/schoolms_dr_drill_' . time() . '.sql';

        $gz  = gzopen($absPath, 'rb');
        $out = fopen($tmpFile, 'wb');

        while (! gzeof($gz)) {
            fwrite($out, gzread($gz, 65536));
        }

        gzclose($gz);
        fclose($out);

        return $tmpFile;
    }

    private function restoreToScratch(string $sqlFile): int
    {
        $cfg  = config('database.connections.mysql');
        $host = $cfg['host']     ?? '127.0.0.1';
        $port = $cfg['port']     ?? '3306';
        $user = $cfg['username'] ?? 'root';
        $pass = $cfg['password'] ?? '';

        // Build restore command targeting the scratch database
        // MYSQL_PWD avoids --password= appearing in process list
        $env = "MYSQL_PWD=" . escapeshellarg($pass);
        $cmd = "{$env} mysql -h " . escapeshellarg($host)
             . " -P " . escapeshellarg((string) $port)
             . " -u " . escapeshellarg($user)
             . " " . escapeshellarg($this->drillDb)
             . " < " . escapeshellarg($sqlFile)
             . " 2>&1";

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error(implode("\n", $output));
        }

        return $exitCode;
    }

    private function verifyRestoredData(): bool
    {
        // Check that critical tables exist in the scratch schema
        $tables = ['tenants', 'users', 'students', 'payments'];
        foreach ($tables as $table) {
            $exists = DB::select("SELECT COUNT(*) as cnt FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?", [$this->drillDb, $table]);
            if (empty($exists) || $exists[0]->cnt === 0) {
                $this->error("      Table '{$table}' not found in restored schema.");
                return false;
            }
        }
        return true;
    }

    private function cleanup(): void
    {
        // Drop scratch schema if it was created
        if ($this->drillDb) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$this->drillDb}`");
            } catch (\Throwable) {
                // Best effort
            }
        }

        // Remove temp decompressed file
        if ($this->tmpFile && file_exists($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    private function dryRunReport(string $backupFile, float $startTime): void
    {
        $checksumFile = $backupFile . '.sha256';
        $hasChecksum  = Storage::disk('local')->exists($checksumFile);

        $this->newLine();
        $this->line('DRY RUN — steps that would be executed:');
        $this->line("  [1] Backup file: {$backupFile}");
        $this->line("  [2] Checksum verification: " . ($hasChecksum ? 'YES (.sha256 found)' : 'SKIPPED (no sidecar)'));
        $this->line('  [3] Decompress .sql.gz → /tmp/schoolms_dr_drill_*.sql');
        $drillDb = config('database.connections.mysql.database') . '_dr_drill';
        $this->line("  [4] Create scratch schema: {$drillDb}");
        $this->line("  [5] mysql restore into scratch schema");
        $this->line("  [6] Verify tables: tenants, users, students, payments");
        $this->line("  [7] DROP {$drillDb} + delete temp file");
        $this->newLine();
        $this->info('Dry run complete — no restore was performed.');
    }
}
