<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Suspended – {{ $tenant->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center px-4">

    <div class="w-full max-w-lg bg-gray-800 rounded-2xl border border-gray-700 overflow-hidden">
        <div class="h-1 bg-red-500"></div>

        <div class="p-8">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-10 w-10 bg-red-500/20 rounded-xl flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-red-400 font-semibold uppercase tracking-wide">Subscription Expired</p>
                    <h1 class="text-white font-bold text-lg">{{ $tenant->name }}</h1>
                </div>
            </div>

            <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 mb-6">
                <p class="text-red-300 text-sm leading-relaxed">
                    Your admin dashboard has been locked due to subscription expiry.
                    All your data is safe and will be fully accessible immediately after renewal.
                </p>
            </div>

            @if($subscription)
                <div class="bg-gray-700/50 rounded-xl p-4 mb-6 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Term</span>
                        <span class="text-white font-medium">
                            {{ $subscription->term?->term_name }}
                            {{ $subscription->term?->academicYear?->year_label }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Amount Due</span>
                        <span class="text-white font-bold text-lg">GHS {{ number_format($subscription->amount, 2) }}</span>
                    </div>
                </div>
            @endif

            <a href="{{ url('/pay/' . $tenant->slug) }}"
               class="block w-full bg-red-600 hover:bg-red-700 text-white font-semibold
                      py-3 px-6 rounded-xl text-center transition-colors mb-3">
                Restore Access — Pay Now
            </a>

            <p class="text-center text-xs text-gray-500">
                Instant activation after payment confirmation · Data fully preserved
            </p>
        </div>
    </div>

</body>
</html>
