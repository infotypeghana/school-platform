<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\BiometricEnrollment;
use App\Models\BiometricLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\BiometricAttendanceService;
use App\Services\ZKTecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BiometricController extends Controller
{
    public function __construct(
        private readonly ZKTecoService             $zk,
        private readonly BiometricAttendanceService $bio,
    ) {}

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(): View
    {
        $devices = BiometricDevice::withCount(['logs', 'enrollments'])
            ->orderBy('name')
            ->get();

        $recentLogs = BiometricLog::with('device')
            ->orderByDesc('verified_at')
            ->limit(50)
            ->get();

        $stats = [
            'total_punches'    => BiometricLog::count(),
            'unmatched'        => BiometricLog::where('is_processed', false)->count(),
            'devices_active'   => BiometricDevice::where('is_active', true)->count(),
            'today'            => BiometricLog::whereDate('verified_at', today())->count(),
        ];

        return view('admin.biometric.index', compact('devices', 'recentLogs', 'stats'));
    }

    // ── Device CRUD ───────────────────────────────────────────────────────────

    public function create(): View
    {
        $device = null;
        return view('admin.biometric.form', compact('device'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'device_serial' => 'nullable|string|max:50',
            'ip_address'    => 'nullable|ip',
            'port'          => 'nullable|integer|min:1|max:65535',
            'password'      => 'nullable|integer|min:0',
            'model'         => 'nullable|string|max:100',
            'location'      => 'nullable|string|max:150',
            'is_active'     => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['port']      = $data['port'] ?? 4370;
        $data['password']  = $data['password'] ?? 0;

        BiometricDevice::create($data);

        return redirect()->route('admin.biometric.index')
            ->with('success', 'Device registered successfully.');
    }

    public function edit(BiometricDevice $device): View
    {
        return view('admin.biometric.form', compact('device'));
    }

    public function update(Request $request, BiometricDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'device_serial' => 'nullable|string|max:50',
            'ip_address'    => 'nullable|ip',
            'port'          => 'nullable|integer|min:1|max:65535',
            'password'      => 'nullable|integer|min:0',
            'model'         => 'nullable|string|max:100',
            'location'      => 'nullable|string|max:150',
            'is_active'     => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $device->update($data);

        return redirect()->route('admin.biometric.index')
            ->with('success', 'Device updated.');
    }

    public function destroy(BiometricDevice $device): RedirectResponse
    {
        $device->delete();
        return redirect()->route('admin.biometric.index')
            ->with('success', 'Device removed.');
    }

    // ── TCP Sync ──────────────────────────────────────────────────────────────

    /**
     * Pull attendance logs from the device via TCP binary protocol.
     */
    public function sync(BiometricDevice $device): RedirectResponse
    {
        if (! $device->ip_address) {
            return back()->with('error', 'No IP address configured for this device. TCP sync requires an IP.');
        }

        $connected = $this->zk->connect(
            $device->ip_address,
            $device->port,
            $device->password,
        );

        if (! $connected) {
            return back()->with('error', "Could not connect to {$device->ip_address}:{$device->port}. Check that the device is online and the IP/port are correct.");
        }

        $punches = $this->zk->getAttendanceLogs();
        $this->zk->disconnect();

        if (empty($punches)) {
            return back()->with('success', 'Connected successfully — no new attendance records on device.');
        }

        $result = $this->bio->ingest($device, $punches);

        return back()->with('success', sprintf(
            'Sync complete: %d imported, %d already stored, %d unmatched (enrol those users first).',
            $result['imported'],
            $result['skipped'],
            $result['unmatched'],
        ));
    }

    // ── Enrollment management ─────────────────────────────────────────────────

    public function enroll(BiometricDevice $device): View
    {
        $enrollments = BiometricEnrollment::where('device_id', $device->id)
            ->orderBy('device_user_id')
            ->get();

        // Unmatched device user IDs from logs (not yet mapped)
        $unmatchedIds = BiometricLog::where('device_id', $device->id)
            ->whereNotIn('device_user_id', $enrollments->pluck('device_user_id'))
            ->distinct()
            ->pluck('device_user_id');

        $students = Student::where('status', 'active')->orderBy('first_name')->get();
        $teachers = Teacher::where('status', 'active')->orderBy('first_name')->get();

        return view('admin.biometric.enroll', compact(
            'device', 'enrollments', 'unmatchedIds', 'students', 'teachers'
        ));
    }

    public function storeEnrollment(Request $request, BiometricDevice $device): RedirectResponse
    {
        $data = $request->validate([
            'device_user_id' => 'required|string|max:20',
            'person_type'    => 'required|in:student,teacher',
            'person_id'      => 'required|integer|min:1',
        ]);

        BiometricEnrollment::updateOrCreate(
            [
                'device_id'      => $device->id,
                'device_user_id' => $data['device_user_id'],
            ],
            [
                'tenant_id'   => app('currentTenant')?->id,
                'person_type' => $data['person_type'],
                'person_id'   => $data['person_id'],
            ]
        );

        // Re-process any unprocessed logs for this user
        $this->reprocessLogs($device->id, $data['device_user_id']);

        return back()->with('success', "User ID {$data['device_user_id']} mapped successfully.");
    }

    public function destroyEnrollment(BiometricEnrollment $enrollment): RedirectResponse
    {
        $enrollment->delete();
        return back()->with('success', 'Enrollment removed.');
    }

    // ── JSON endpoint: recent punches for live feed ───────────────────────────

    public function recentLogs(): JsonResponse
    {
        $logs = BiometricLog::with('device')
            ->orderByDesc('verified_at')
            ->limit(20)
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'user_id'     => $log->device_user_id,
                'device'      => $log->device?->name,
                'time'        => $log->verified_at->format('H:i:s'),
                'date'        => $log->verified_at->format('d M'),
                'verify_type' => $log->verify_label,
                'direction'   => $log->direction_label,
                'processed'   => $log->is_processed,
            ]);

        return response()->json($logs);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * After a new enrollment is saved, retroactively process any stored but
     * unprocessed BiometricLog rows for that device user.
     *
     * We call processStoredLog() directly instead of re-running ingest() because
     * ingest() deduplicates by (device_id, device_user_id, verified_at) and would
     * skip every existing log — leaving is_processed = false forever.
     */
    private function reprocessLogs(int $deviceId, string $deviceUserId): void
    {
        $enrollment = BiometricEnrollment::where('device_id', $deviceId)
            ->where('device_user_id', $deviceUserId)
            ->first();

        if (! $enrollment) {
            return;
        }

        $unprocessed = BiometricLog::where('device_id', $deviceId)
            ->where('device_user_id', $deviceUserId)
            ->where('is_processed', false)
            ->get();

        foreach ($unprocessed as $log) {
            $attendance = $this->bio->processStoredLog($log, $enrollment);

            if ($enrollment->person_type !== 'student') {
                // processStoredLog() marks teacher logs directly; nothing else to do.
                continue;
            }

            if ($attendance) {
                $log->update(['is_processed' => true, 'attendance_id' => $attendance->id]);
            }
        }
    }
}
