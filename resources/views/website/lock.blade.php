<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $tenant->name }} – Portal Unavailable</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gray-50 flex flex-col items-center justify-center px-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

        {{-- Top red bar --}}
        <div class="h-2 bg-red-600"></div>

        <div class="p-8 text-center">

            {{-- School logo --}}
            @if($tenant->logo)
                <img src="{{ asset('storage/' . $tenant->logo) }}"
                     alt="{{ $tenant->name }}"
                     class="h-20 w-20 object-contain rounded-full mx-auto mb-4 border border-gray-200">
            @else
                <div class="h-20 w-20 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                </div>
            @endif

            {{-- Lock icon --}}
            <div class="flex items-center justify-center mb-4">
                <svg class="h-8 w-8 text-red-500 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="text-red-600 font-semibold text-sm uppercase tracking-wide">Portal Unavailable</span>
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-2">{{ $tenant->name }}</h1>

            <p class="text-gray-500 text-sm leading-relaxed mb-6">
                This school portal is currently unavailable due to subscription expiry.
                Access will be restored <strong>immediately</strong> after renewal.
            </p>

            {{-- Renewal amount if available --}}
            @if($subscription && $subscription->amount > 0)
                <div class="bg-red-50 border border-red-100 rounded-xl p-4 mb-6">
                    <p class="text-sm text-red-700">Amount due for renewal</p>
                    <p class="text-2xl font-bold text-red-600 mt-1">
                        GHS {{ number_format($subscription->amount, 2) }}
                    </p>
                    @if($subscription->term)
                        <p class="text-xs text-red-500 mt-1">
                            {{ $subscription->term->term_name }}
                            {{ $subscription->term->academicYear->year_label ?? '' }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Renew button --}}
            <a href="{{ url('/pay/' . $tenant->slug) }}"
               class="block w-full bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-semibold
                      py-3 px-6 rounded-xl transition-colors duration-200 text-center mb-4">
                Renew Subscription
            </a>

            {{-- Contact info --}}
            @if($tenant->contact_phone || $tenant->contact_email)
                <div class="border-t border-gray-100 pt-4 text-sm text-gray-400 space-y-1">
                    @if($tenant->contact_phone)
                        <p>
                            <span class="font-medium text-gray-500">Call:</span>
                            {{ $tenant->contact_phone }}
                        </p>
                    @endif
                    @if($tenant->contact_email)
                        <p>
                            <span class="font-medium text-gray-500">Email:</span>
                            {{ $tenant->contact_email }}
                        </p>
                    @endif
                </div>
            @endif

        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-8 py-3 text-center border-t border-gray-100">
            <p class="text-xs text-gray-400">Powered by SchoolMS · Ghana School Management Platform</p>
        </div>

    </div>

</body>
</html>
