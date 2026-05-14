<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-100">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Super Admin') — SchoolMS Ghana</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full" x-data>

<div class="min-h-screen flex">

  {{-- Sidebar --}}
  <aside class="w-64 bg-gray-900 text-white flex flex-col flex-shrink-0">
    <div class="px-5 py-5 border-b border-gray-800">
      <div class="text-xs text-gray-500 uppercase tracking-widest mb-0.5">SchoolMS Ghana</div>
      <div class="font-bold text-white text-base">Super Admin</div>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-0.5">
      @foreach([
        ['superadmin.dashboard',          'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', 'Dashboard'],
        ['superadmin.tenants.index',      'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'Schools'],
        ['superadmin.academic-years.index','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'Academic Years'],
        ['superadmin.academic-terms.index','M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'Terms'],
        ['superadmin.subscriptions',      'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z', 'Subscriptions'],
        ['superadmin.payments',           'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', 'Payments'],
      ] as [$route, $icon, $label])
        @php $active = request()->routeIs($route . '*'); @endphp
        <a href="{{ route($route) }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors
             {{ $active ? 'bg-blue-600 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' }}">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $icon }}"/>
          </svg>
          {{ $label }}
        </a>
      @endforeach
    </nav>

    <div class="px-4 py-4 border-t border-gray-800 text-xs text-gray-500">
      SchoolMS Ghana v1.0
    </div>
  </aside>

  {{-- Main --}}
  <div class="flex-1 flex flex-col min-w-0">

    {{-- Top bar --}}
    <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
      <h1 class="text-lg font-semibold text-gray-900">@yield('page-title', 'Dashboard')</h1>
      <div class="flex items-center gap-4">
        <span class="text-sm text-gray-500">Super Administrator</span>
        <form method="POST" action="{{ route('superadmin.logout') }}" class="inline">
          @csrf
          <button class="text-sm text-red-500 hover:text-red-700 transition-colors">Logout</button>
        </form>
      </div>
    </header>

    {{-- Content --}}
    <main class="flex-1 p-6 overflow-auto">
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
