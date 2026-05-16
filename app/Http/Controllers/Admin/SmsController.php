<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendSmsJob;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    public function index(Request $request): View
    {
        $logs = SmsLog::when($request->channel, fn ($q) => $q->where('channel', $request->channel))
            ->when($request->status,  fn ($q) => $q->where('status',  $request->status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total'   => SmsLog::count(),
            'sent'    => SmsLog::where('status', 'sent')->count(),
            'failed'  => SmsLog::where('status', 'failed')->count(),
            'sms'     => SmsLog::where('channel', 'sms')->count(),
            'wa'      => SmsLog::where('channel', 'whatsapp')->count(),
        ];

        return view('admin.sms.index', compact('logs', 'stats'));
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient' => 'required|string|max:20',
            'message'   => 'required|string|max:160',
            'channel'   => 'required|in:sms,whatsapp',
        ]);

        $tenant = app('currentTenant');

        $ok = $data['channel'] === 'whatsapp'
            ? $this->sms->sendWhatsApp($data['recipient'], $data['message'], $tenant)
            : $this->sms->send($data['recipient'], $data['message'], $tenant);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Message dispatched successfully.' : 'Message failed to send — check the log below.'
        );
    }

    // ── Bulk broadcast ────────────────────────────────────────────────────────

    /**
     * POST /sms/broadcast
     *
     * Dispatches one SendSmsJob per unique guardian / teacher phone.
     * Target audiences:
     *   all_parents      → all active students' guardian phones
     *   class:{id}       → guardian phones for one class
     *   all_teachers     → all active teacher contact phones
     *   overdue_fees     → guardians of students with unpaid fee balance
     */
    public function broadcast(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'audience' => ['required', 'string'],
            'message'  => ['required', 'string', 'min:5', 'max:320'],
            'channel'  => ['required', 'in:sms,whatsapp'],
        ]);

        $tenant  = app('currentTenant');
        $message = trim($data['message']);
        $channel = $data['channel'];
        $phones  = $this->resolveAudience($data['audience']);

        if ($phones->isEmpty()) {
            return back()->with('error', 'No recipients found for the selected audience.');
        }

        foreach ($phones as $phone) {
            SendSmsJob::dispatch($phone, $message, $tenant, null, $channel)
                ->onQueue('default');
        }

        $count = $phones->count();

        return back()->with('success', "Broadcast queued for {$count} recipient(s).");
    }

    /**
     * Resolve audience key → collection of unique, non-empty phone numbers.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveAudience(string $audience): \Illuminate\Support\Collection
    {
        return match (true) {
            $audience === 'all_parents' => $this->guardianPhones(),

            str_starts_with($audience, 'class:') => $this->guardianPhones(
                (int) str_replace('class:', '', $audience)
            ),

            $audience === 'all_teachers' => Teacher::where('status', 'active')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->pluck('phone')
                ->unique()
                ->values(),

            $audience === 'overdue_fees' => Student::where('status', 'active')
                ->whereHas('fees', fn ($q) => $q->where('balance', '>', 0))
                ->whereNotNull('guardian_phone')
                ->where('guardian_phone', '!=', '')
                ->pluck('guardian_phone')
                ->unique()
                ->values(),

            default => collect(),
        };
    }

    /**
     * Guardian phones for all active students, optionally filtered by class.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function guardianPhones(?int $classId = null): \Illuminate\Support\Collection
    {
        return Student::where('status', 'active')
            ->when($classId, fn ($q) => $q->where('school_class_id', $classId))
            ->whereNotNull('guardian_phone')
            ->where('guardian_phone', '!=', '')
            ->pluck('guardian_phone')
            ->unique()
            ->values();
    }
}
