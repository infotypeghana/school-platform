<?php

use App\Console\Commands\DatabaseBackupCommand;
use App\Console\Commands\DisasterRecoveryDrillCommand;
use App\Console\Commands\GenerateTermInvoicesCommand;
use App\Console\Commands\PruneAuditLogsCommand;
use App\Console\Commands\PruneRequestLogsCommand;
use App\Console\Commands\ReconcilePaymentsCommand;
use App\Console\Commands\SyncBiometricAttendance;
use App\Jobs\CheckSubscriptionStatusJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Horizon\Horizon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Subscription Status Cron — runs daily at 06:00 WAT (UTC+0 = 06:00 UTC)
|--------------------------------------------------------------------------
| Transitions: active/trial → grace → locked
| Sends: pre-expiry and grace notifications
*/
Schedule::job(CheckSubscriptionStatusJob::class)
    ->dailyAt('06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('CheckSubscriptionStatusJob failed in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| Biometric TCP Sync — every 15 minutes during school hours
|--------------------------------------------------------------------------
| Pulls attendance logs from all registered ZKTeco devices via TCP.
| ADMS push is the primary path; this is a fallback for older devices.
*/
Schedule::command(SyncBiometricAttendance::class)
    ->everyFifteenMinutes()
    ->timezone(config('app.timezone'))
    ->between('06:00', '18:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::warning('SyncBiometricAttendance scheduled sync failed.');
    });

/*
|--------------------------------------------------------------------------
| Horizon Metrics Snapshot — every 5 minutes
|--------------------------------------------------------------------------
| Stores queue throughput and wait-time snapshots used by the Horizon
| metrics graphs. Without this, the Horizon dashboard shows no history.
| Requires QUEUE_CONNECTION=redis in production.
*/
Schedule::command('horizon:snapshot')->everyFiveMinutes();

/*
|--------------------------------------------------------------------------
| Term Invoice Generation — 1st day of each month at 07:00 WAT
|--------------------------------------------------------------------------
| Generates subscription invoices for all active schools that have a
| package attached and haven't been invoiced for the current term yet.
| Run manually with: php artisan invoices:generate [--dry-run]
*/
Schedule::command(GenerateTermInvoicesCommand::class)
    ->monthlyOn(1, '07:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('GenerateTermInvoicesCommand failed in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| Database Backup — daily at 02:00 local time
|--------------------------------------------------------------------------
| Dumps the MySQL database to storage/app/backups/ as a gzipped SQL file.
| Rotates backups older than 7 days automatically.
| Requires mysqldump to be installed on the server ($PATH).
*/
Schedule::command(DatabaseBackupCommand::class)
    ->dailyAt('02:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('DatabaseBackupCommand failed in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| Audit Log Pruning — first Sunday of each month at 03:00 WAT
|--------------------------------------------------------------------------
| Deletes audit_log rows older than 365 days to prevent unbounded growth.
| Adjust --days to match your data-retention policy or regulatory needs.
*/
Schedule::command(PruneAuditLogsCommand::class, ['--days=365'])
    ->monthlyOn(1, '03:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('PruneAuditLogsCommand failed in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| DR Restore Drill — 1st of every month at 03:30 WAT
|--------------------------------------------------------------------------
| Restores the latest backup into a scratch schema, verifies table
| integrity, then drops the scratch schema. Does NOT touch production data.
| Results are logged; failure sends an email to SUPER_ADMIN_EMAIL.
*/
Schedule::command(DisasterRecoveryDrillCommand::class)
    ->monthlyOn(1, '03:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical('DisasterRecoveryDrillCommand FAILED in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| Payment Reconciliation — weekly on Sunday at 04:00 WAT
|--------------------------------------------------------------------------
| Detects orphaned payments, duplicate references, and unactivated
| subscriptions. Results are logged. Use --heal to auto-fix orphans.
*/
Schedule::command(ReconcilePaymentsCommand::class)
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('ReconcilePaymentsCommand failed in scheduler.');
    });

/*
|--------------------------------------------------------------------------
| Request Log Pruning — nightly at 01:00 WAT
|--------------------------------------------------------------------------
| Deletes request_log rows older than 90 days to prevent unbounded table
| growth. Chunked deletion (5 000 rows per batch) avoids table locks.
| Adjust --days to match your data-retention / GDPR policy.
*/
Schedule::command(PruneRequestLogsCommand::class, ['--days=90', '--chunk=5000'])
    ->dailyAt('01:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('PruneRequestLogsCommand failed in scheduler.');
    });
