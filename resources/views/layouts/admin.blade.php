<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ app('currentTenant')?->name ?? 'SchoolMS' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap'); body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="h-full" x-data>

    {{-- ═══════════════════════════════════════════════════════════════
         NON-DISMISSIBLE GRACE BANNER (admin version)
         Shows on EVERY admin page during grace period.
         Cannot be dismissed — business pressure is intentional.
    ═══════════════════════════════════════════════════════════════ --}}
    @if(isset($graceSubscription))
        @php
            $days    = $graceDaysRemaining ?? 0;
            $urgent  = $days < 3;
            $amount  = 'GHS ' . number_format($graceSubscription->amount, 2);
            $tenant  = app('currentTenant');
        @endphp
        <div class="{{ $urgent ? 'bg-red-600' : 'bg-amber-500' }} text-white px-4 py-3">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <div>
                        <p class="font-semibold text-sm">
                            @if($days === 0)
                                Platform locks <strong>tonight at midnight</strong>.
                            @elseif($days === 1)
                                Platform locks <strong>tomorrow</strong> — Last chance to pay.
                            @else
                                Subscription in grace period — <strong>{{ $days }} days remaining</strong> before full lock.
                            @endif
                        </p>
                        <p class="text-xs text-white/80 mt-0.5">
                            Your admin dashboard and public website will be inaccessible after grace expires.
                            Amount due: <strong>{{ $amount }}</strong> for {{ $graceSubscription->term?->term_name }}.
                        </p>
                    </div>
                </div>
                <a href="{{ url('/pay/' . $tenant?->slug) }}"
                   class="flex-shrink-0 bg-white text-gray-900 font-semibold text-sm px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors whitespace-nowrap">
                    Pay Now — {{ $amount }}
                </a>
            </div>
        </div>
    @endif

    <div class="flex h-full">

        {{-- ── Sidebar ─────────────────────────────────────────────── --}}
        <aside class="hidden lg:flex lg:flex-col w-64 bg-gray-900 border-r border-gray-800 fixed inset-y-0 left-0 z-10">
            {{-- Logo --}}
            <div class="h-16 flex items-center px-6 border-b border-gray-800">
                @php $tenant = app('currentTenant'); @endphp
                @if($tenant?->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="h-8 w-8 rounded-full mr-3">
                @else
                    <div class="h-8 w-8 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                        <span class="text-white text-xs font-bold">{{ strtoupper(substr($tenant?->name ?? 'S', 0, 1)) }}</span>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-white text-sm font-semibold truncate">{{ $tenant?->name }}</p>
                    <p class="text-gray-400 text-xs">Admin Panel</p>
                </div>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                @php
                    $navItems = [
                        ['icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'label' => 'Dashboard',   'route' => 'admin.dashboard'],
                        ['icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Students',    'route' => 'admin.students'],
                        ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Teachers',    'route' => 'admin.teachers'],
                        ['icon' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10', 'label' => 'Classes',     'route' => 'admin.classes'],
                        ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', 'label' => 'Attendance',  'route' => 'admin.attendance'],
                        ['icon' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Report Cards', 'route' => 'admin.report-cards'],
                        ['icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Fees',         'route' => 'admin.fees'],
                        ['icon' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z', 'label' => 'Feeding Fees', 'route' => 'admin.feeding.index'],
                        ['icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', 'label' => 'Lesson Notes', 'route' => 'admin.lesson-notes.index'],
                        ['icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Timetable',      'route' => 'admin.timetables.index'],
                        ['icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'label' => 'Admissions',     'route' => 'admin.admissions.index'],
                        ['icon' => 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z', 'label' => 'Announcements',  'route' => 'admin.announcements.index'],
                        ['icon' => 'M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z M13 10a1 1 0 011-1h3a1 1 0 110 2h-3a1 1 0 01-1-1z', 'label' => 'Analytics',       'route' => 'admin.analytics.index'],
                        ['icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Audit Trail',     'route' => 'admin.audit.index'],
                        ['icon' => 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4', 'label' => 'API Docs',         'route' => 'admin.api.docs'],
                        ['icon' => 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4', 'label' => 'Backup / Export',  'route' => 'admin.backup.index'],
                        ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'label' => 'Finance',         'route' => 'admin.finance.index'],
                        ['icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z', 'label' => 'SMS / WhatsApp',  'route' => 'admin.sms.index'],
                        ['icon' => 'M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11', 'label' => 'Biometric',       'route' => 'admin.biometric.index'],
                        ['icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'label' => 'Subscription',    'route' => 'admin.subscription'],
                        ['icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'label' => 'Settings',        'route' => 'admin.settings.school'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                              {{ request()->routeIs($item['route'])
                              || ($item['route'] === 'admin.settings.school'      && request()->routeIs('admin.settings.*'))
                              || ($item['route'] === 'admin.announcements.index'  && request()->routeIs('admin.announcements.*'))
                              || ($item['route'] === 'admin.finance.index'        && request()->routeIs('admin.finance.*'))
                              || ($item['route'] === 'admin.timetables.index'     && request()->routeIs('admin.timetables.*'))
                              || ($item['route'] === 'admin.admissions.index'     && request()->routeIs('admin.admissions.*'))
                              || ($item['route'] === 'admin.biometric.index'      && request()->routeIs('admin.biometric.*'))
                              || ($item['route'] === 'admin.audit.index'          && request()->routeIs('admin.audit.*'))
                              || ($item['route'] === 'admin.analytics.index'      && request()->routeIs('admin.analytics.*'))
                              || ($item['route'] === 'admin.feeding.index'        && request()->routeIs('admin.feeding.*'))
                              || ($item['route'] === 'admin.lesson-notes.index'   && request()->routeIs('admin.lesson-notes.*'))
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            {{-- Footer --}}
            <div class="px-4 py-3 border-t border-gray-800 text-xs text-gray-500">
                SchoolMS Ghana · {{ now()->format('Y') }}
            </div>
        </aside>

        {{-- ── Main content ─────────────────────────────────────────── --}}
        <div class="flex-1 flex flex-col lg:ml-64 min-h-screen">

            {{-- Top bar --}}
            <header class="h-16 bg-white border-b border-gray-200 px-6 flex items-center justify-between flex-shrink-0">
                <h1 class="text-lg font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500">{{ auth()->user()?->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit"
                                class="text-sm text-gray-400 hover:text-gray-700 transition-colors">
                            Sign out
                        </button>
                    </form>
                </div>
            </header>

            {{-- Page body --}}
            <main class="flex-1 px-6 py-6">
                @if(session('success'))
                    <div class="mb-4 bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

    @stack('scripts')
</body>
</html>
