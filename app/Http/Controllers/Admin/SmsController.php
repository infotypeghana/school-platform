<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
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
}
