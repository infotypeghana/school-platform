<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Services\BiometricAttendanceService;
use App\Services\ZKTecoService;
use Illuminate\Console\Command;

class SyncBiometricAttendance extends Command
{
    protected $signature = 'biometric:sync
                            {device? : Device ID to sync (omit for all active devices)}
                            {--dry-run : Parse and count records but do not save}';

    protected $description = 'Pull attendance logs from ZKTeco devices via TCP';

    public function __construct(
        private readonly ZKTecoService             $zk,
        private readonly BiometricAttendanceService $bio,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $deviceId = $this->argument('device');
        $dryRun   = $this->option('dry-run');

        $query = BiometricDevice::withoutTenantScope()
            ->where('is_active', true)
            ->whereNotNull('ip_address');

        if ($deviceId) {
            $query->where('id', $deviceId);
        }

        $devices = $query->get();

        if ($devices->isEmpty()) {
            $this->warn('No active TCP-capable devices found.');
            return self::FAILURE;
        }

        foreach ($devices as $device) {
            $this->info("Syncing: {$device->name} ({$device->ip_address}:{$device->port})");

            $connected = $this->zk->connect(
                $device->ip_address,
                $device->port,
                $device->password,
                timeout: 10,
            );

            if (! $connected) {
                $this->error("  ✗ Could not connect.");
                continue;
            }

            $punches = $this->zk->getAttendanceLogs();
            $this->zk->disconnect();

            $this->line("  → {$this->count($punches)} record(s) found on device.");

            if ($dryRun) {
                $this->warn('  [dry-run] Skipping database write.');
                continue;
            }

            if (empty($punches)) {
                $device->update(['last_sync_at' => now()]);
                $this->line('  ✓ No new records.');
                continue;
            }

            $result = $this->bio->ingest($device, $punches);

            $this->info(sprintf(
                '  ✓ Imported: %d  |  Skipped: %d  |  Unmatched: %d',
                $result['imported'],
                $result['skipped'],
                $result['unmatched'],
            ));
        }

        return self::SUCCESS;
    }

    private function count(array $arr): int
    {
        return count($arr);
    }
}
