<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment {{ ucfirst($status) }} — SchoolMS</title>
<script src="https://cdn.tailwindcss.com"></script>
@if($status === 'pending')
    {{-- Auto-refresh after 5 seconds to check if webhook has fired --}}
    <meta http-equiv="refresh" content="6">
@endif
</head>
<body class="h-full bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

        @if($status === 'success')
        {{-- ── Success ─────────────────────────────────────────────────── --}}
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 px-8 py-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Payment Confirmed!</h1>
            <p class="text-emerald-100 text-sm mt-1">Your subscription is now active.</p>
        </div>
        <div class="px-8 py-7 space-y-4">
            @if(isset($tenant))
                <div class="bg-gray-50 rounded-xl px-4 py-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">School</span>
                        <span class="font-medium text-gray-800">{{ $tenant->name }}</span>
                    </div>
                    @if(isset($payment))
                        <div class="flex justify-between">
                            <span class="text-gray-500">Amount Paid</span>
                            <span class="font-medium text-gray-800">GHS {{ number_format($payment->amount, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Reference</span>
                            <span class="font-mono text-xs text-gray-600">{{ $payment->reference }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Paid At</span>
                            <span class="text-gray-800">{{ $payment->paid_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}</span>
                        </div>
                    @endif
                </div>
            @endif
            <p class="text-sm text-gray-500 text-center">
                Your school admin portal is fully accessible. You can now close this page.
            </p>
            @if(isset($tenant))
                <a href="https://{{ $tenant->slug }}.admin.{{ config('app.domain') }}/dashboard"
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-semibold py-3 rounded-xl transition-colors text-sm">
                    Go to Admin Portal →
                </a>
            @endif
        </div>

        @elseif($status === 'pending')
        {{-- ── Pending / processing ─────────────────────────────────────── --}}
        <div class="bg-gradient-to-r from-amber-500 to-orange-500 px-8 py-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Confirming Payment…</h1>
            <p class="text-amber-100 text-sm mt-1">This page will refresh automatically.</p>
        </div>
        <div class="px-8 py-7 space-y-3 text-center">
            <p class="text-sm text-gray-600 leading-relaxed">
                Your payment is being confirmed with the gateway. This usually takes a few seconds.
                Please do not close this page.
            </p>
            @if(isset($payment))
                <p class="text-xs text-gray-400 font-mono">Ref: {{ $payment->reference }}</p>
            @endif
            <div class="pt-2 text-xs text-gray-400">
                If this takes more than 2 minutes, please contact support.
            </div>
        </div>

        @elseif($status === 'cancelled')
        {{-- ── Cancelled ────────────────────────────────────────────────── --}}
        <div class="bg-gradient-to-r from-gray-500 to-gray-600 px-8 py-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Payment Cancelled</h1>
            <p class="text-gray-200 text-sm mt-1">No charge was made to your account.</p>
        </div>
        <div class="px-8 py-7 space-y-4 text-center">
            <p class="text-sm text-gray-500">
                You cancelled the payment. Your subscription status has not changed.
            </p>
            @if(isset($payment) && $payment->tenant)
                <a href="{{ route('payment.page', ['slug' => $payment->tenant->slug]) }}"
                   class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
                    Try Again
                </a>
            @endif
        </div>

        @else
        {{-- ── Error ───────────────────────────────────────────────────── --}}
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-8 py-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">Payment Error</h1>
            <p class="text-red-200 text-sm mt-1">Something went wrong.</p>
        </div>
        <div class="px-8 py-7 text-center space-y-3">
            @if(isset($message))
                <p class="text-sm text-gray-600">{{ $message }}</p>
            @else
                <p class="text-sm text-gray-500">
                    We couldn't confirm your payment. If you were charged, please contact support with your reference number.
                </p>
            @endif
        </div>
        @endif

    </div>
    <p class="text-center text-xs text-blue-200 mt-4">
        SchoolMS Ghana · Secure payments via Paystack &amp; Flutterwave
    </p>
</div>
</body>
</html>
