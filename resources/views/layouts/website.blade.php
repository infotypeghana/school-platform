<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', app('currentTenant')?->name ?? 'School')</title>
    <meta name="description" content="@yield('meta-description', app('currentTenant')?->name . ' — Official School Website')">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
    @stack('head')
</head>
<body class="bg-white text-gray-900" x-data>

    {{-- ═══════════════════════════════════════════════════════════════
         GRACE BANNER — Public Website (dismissible per session)
         Injected by WebsiteSubscriptionMiddleware during grace period.
    ═══════════════════════════════════════════════════════════════ --}}
    @include('components.grace-banner')

    {{-- ── Navigation ─────────────────────────────────────────────── --}}
    @php $tenant = app('currentTenant'); @endphp
    <nav class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Logo --}}
                <a href="{{ route('website.home') }}" class="flex items-center gap-3">
                    @if($tenant?->logo)
                        <img src="{{ asset('storage/' . $tenant->logo) }}"
                             alt="{{ $tenant->name }}"
                             class="h-10 w-10 object-contain rounded-full">
                    @else
                        <div class="h-10 w-10 rounded-full flex items-center justify-center"
                             style="background-color: {{ $tenant?->primary_color ?? '#1a56db' }}">
                            <span class="text-white text-sm font-bold">
                                {{ strtoupper(substr($tenant?->name ?? 'S', 0, 2)) }}
                            </span>
                        </div>
                    @endif
                    <span class="font-bold text-gray-900 text-lg hidden sm:block">{{ $tenant?->name }}</span>
                </a>

                {{-- Desktop nav links --}}
                <div class="hidden md:flex items-center gap-1">
                    @foreach([
                        ['Home',       'website.home'],
                        ['About',      'website.about'],
                        ['Academics',  'website.academics'],
                        ['Admissions', 'website.admissions'],
                        ['News',       'website.news'],
                        ['Gallery',    'website.gallery'],
                        ['Contact',    'website.contact'],
                    ] as [$label, $routeName])
                        <a href="{{ route($routeName) }}"
                           class="px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                  {{ request()->routeIs($routeName)
                                        ? 'text-blue-600 bg-blue-50'
                                        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                {{-- Portal links --}}
                <div class="hidden md:flex items-center gap-2 ml-2">
                    <a href="{{ session()->has('parent_portal_student_id') ? route('website.portal.dashboard') : route('website.portal') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-500
                              border border-gray-200 hover:border-blue-200 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                        {{ session()->has('parent_portal_student_id') ? 'My Portal' : 'Parent Portal' }}
                    </a>
                    <a href="{{ route('teacher.portal.login') }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-500
                              border border-gray-200 hover:border-blue-200 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                        Teacher Login
                    </a>
                </div>

                {{-- Mobile menu toggle --}}
                <button x-data="{ open: false }"
                        @click="open = !open"
                        class="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100"
                        aria-label="Menu">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    {{-- ── Page content ────────────────────────────────────────────── --}}
    <main>
        @yield('content')
    </main>

    {{-- ── Footer ─────────────────────────────────────────────────── --}}
    <footer class="bg-gray-900 text-gray-300 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-white font-bold text-lg mb-3">{{ $tenant?->name }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $tenant?->address }}</p>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3">Quick Links</h4>
                    <ul class="space-y-2 text-sm">
                        @foreach(['About', 'Academics', 'Admissions', 'News', 'Contact'] as $link)
                            <li>
                                <a href="{{ route('website.' . strtolower($link)) }}"
                                   class="hover:text-white transition-colors">{{ $link }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3">Contact</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        @if($tenant?->contact_phone)
                            <li>📞 {{ $tenant->contact_phone }}</li>
                        @endif
                        @if($tenant?->contact_email)
                            <li>✉️ {{ $tenant->contact_email }}</li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-6 flex flex-col sm:flex-row justify-between items-center gap-2">
                <p class="text-xs text-gray-500">© {{ now()->year }} {{ $tenant?->name }}. All rights reserved.</p>
                <p class="text-xs text-gray-600">Powered by <a href="#" class="hover:text-gray-400">SchoolMS Ghana</a></p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
