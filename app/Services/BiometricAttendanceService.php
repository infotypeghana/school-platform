<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\Attendance;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Converts raw biometric punches into school Attendance records.
 */
class BiometricAttendanceService
{
    /**
     * Ingest a batch of raw punch records (from ADMS push or TCP pull),
     * deduplicate, map to students/teachers, and create/update Attendance rows.
     *
     * @param  BiometricDevice                                                      $device
     * @param  array<int, array{user_id: string, timestamp: Carbon, verify_type: int, direction: int}> $punches
     * @return array{imported: int, skipped: int, unmatched: int}
     */
    public function ingest(BiometricDevice $device, array $punches): array
    {
        $imported  = 0;
        $skipped   = 0;
        $unmatched = 0;

        // Pre-load enrollments for this device (avoid N+1)
        $enrollments = BiometricEnrollment::where('device_id', $device->id)
            ->get()
            ->keyBy('device_user_id');

        foreach ($punches as $punch) {
            $userId    = (string) $punch['user_id'];
            $timestamp = $punch['timestamp'];
            $verifType = (int) ($punch['verify_type'] ?? 0);
            $direction = (int) ($punch['direction']   ?? 0);

            // Deduplicate — skip if this exact punch was already stored
            $exists = BiometricLog::where('device_id',      $device->id)
                ->where('device_user_id', $userId)
                ->where('verified_at',    $timestamp)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Create raw log
            $log = BiometricLog::create([
                'tenant_id'      => $device->tenant_id,
                'device_id'      => $device->id,
                'device_user_id' => $userId,
                'verified_at'    => $timestamp,
                'verify_type'    => $verifType,
                'direction'      => $direction,
                'source'         => 'adms',
            ]);

            // Map to person via enrollment
            $enrollment = $enrollments->get($userId);

            if (! $enrollment) {
                $unmatched++;
                continue;
            }

            if ($enrollment->person_type !== 'student') {
                // Teacher biometric — mark log processed, no student attendance created
                $log->update(['is_processed' => true]);
                $imported++;
                continue;
            }

            // Create student attendance
            $attendance = $this->upsertAttendance(
                $device->tenant_id,
                $enrollment->person_id,
                $timestamp,
                $direction,
            );

            if ($attendance) {
                $log->update(['is_processed' => true, 'attendance_id' => $attendance->id]);
                $imported++;
            }
        }

        // Update device sync timestamp
        $device->update(['last_sync_at' => now()]);

        Log::info("BiometricAttendance: device={$device->id} imported={$imported} skipped={$skipped} unmatched={$unmatched}");

        return compact('imported', 'skipped', 'unmatched');
    }

    /**
     * Attempt to match a device serial to a registered device for this request.
     * Returns null if not found (ADMS endpoint should reject unknown serials).
     */
    public function findDeviceBySerial(string $serial): ?BiometricDevice
    {
        return BiometricDevice::withoutTenantScope()
            ->where('device_serial', $serial)
            ->where('is_active', true)
            ->first();
    }

    // ── Public helpers ────────────────────────────────────────────────────────

    /**
     * Process a single already-stored BiometricLog that was previously unmatched
     * (no enrollment existed at ingest time). Called after a new enrollment is
     * saved so retroactive punches create attendance records without going through
     * ingest() — which would skip the log as a duplicate.
     *
     * Returns the Attendance record on success, null on failure.
     */
    public function processStoredLog(BiometricLog $log, BiometricEnrollment $enrollment): ?Attendance
    {
        if ($enrollment->person_type !== 'student') {
            // Teacher log — just mark it processed
            $log->update(['is_processed' => true]);
            return null;
        }

        return $this->upsertAttendance(
            $log->tenant_id,
            $enrollment->person_id,
            $log->verified_at,
            $log->direction,
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function upsertAttendance(
        int    $tenantId,
        int    $studentId,
        Carbon $timestamp,
        int    $direction,
    ): ?Attendance {
        // direction 0 = in → "present", direction 1 = out → still present
        // We always record as "present" for school context
        $status = 'present';

        $term = AcademicTerm::where('is_current', true)->first();

        try {
            return Attendance::updateOrCreate(
                [
                    'tenant_id'  => $tenantId,
                    'student_id' => $studentId,
                    'date'       => $timestamp->toDateString(),
                ],
                [
                    'status'          => $status,
                    'term_id'         => $term?->id,
                    'school_class_id' => \App\Models\Student::withoutTenantScope()
                        ->find($studentId)?->school_class_id,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("BiometricAttendance: failed to upsert attendance — " . $e->getMessage());
            return null;
        }
    }
}
