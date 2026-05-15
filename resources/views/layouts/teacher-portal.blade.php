<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Teacher Portal') — {{ app('currentTenant')?->name ?? 'School' }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
</style>
@stack('head')
</head>
<body class="bg-gray-50 min-h-screen" x-data>

@php $tenant = app('currentTenant'); @endphp

{{-- ── Top nav ─────────────────────────────────────────────────────────── --}}
<nav class="bg-white border-b border-gray-200 sticky top-0 z-40 shadow-sm">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-14">

            {{-- Brand --}}
            <div class="flex items-center gap-3">
                @if($tenant?->logo)
                    <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
                         class="h-8 w-8 rounded-full object-contain">
                @else
                    <div class="h-8 w-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                         style="background-color: {{ $tenant?->primary_color ?? '#1a56db' }}">
                        {{ strtoupper(substr($tenant?->name ?? 'S', 0, 2)) }}
                    </div>
                @endif
                <span class="text-sm font-semibold text-gray-800">
                    {{ $tenant?->name ?? 'School' }}
                    <span class="text-gray-400 font-normal">· Teacher Portal</span>
                </span>
            </div>

            {{-- Nav links + logout --}}
            @isset($authTeacher)
            <div class="flex items-center gap-4">
                <div class="hidden sm:flex items-center gap-1 text-sm">
                    <a href="{{ route('teacher.portal.dashboard') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.dashboard') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('teacher.portal.scores') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.scores*') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Scores
                    </a>
                    <a href="{{ route('teacher.portal.timetable') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.timetable') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Timetable
                    </a>
                    @if(isset($authTeacher) && \App\Models\SchoolClass::where('class_teacher_id', $authTeacher->id)->exists())
                    <a href="{{ route('teacher.portal.remarks') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.remarks*') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Remarks
                    </a>
                    @endif
                    <a href="{{ route('teacher.portal.lesson-notes.index') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.lesson-notes*') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Lesson Notes
                    </a>
                    <a href="{{ route('teacher.portal.schemes.index') }}"
                       class="px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors
                              {{ request()->routeIs('teacher.portal.schemes*') ? 'bg-blue-50 text-blue-700 font-medium' : '' }}">
                        Schemes
                    </a>
                </div>

                {{-- Teacher dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 focus:outline-none">
                        <div class="h-7 w-7 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-xs font-bold text-blue-700">
                                {{ strtoupper(substr($authTeacher->first_name, 0, 1) . substr($authTeacher->last_name, 0, 1)) }}
                            </span>
                        </div>
                        <span class="hidden sm:block font-medium">{{ $authTeacher->first_name }}</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-transition
                         class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-50">
                        <div class="px-4 py-2 border-b border-gray-50">
                            <p class="text-xs font-semibold text-gray-800">{{ $authTeacher->full_name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $authTeacher->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('teacher.portal.logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endisset
        </div>
    </div>
</nav>

{{-- ── Page content ────────────────────────────────────────────────────── --}}
<main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
            @foreach($errors->all() as $e)
                <p>{{ $e }}</p>
            @endforeach
        </div>
    @endif

    @yield('content')
</main>

@stack('scripts')
</body>
</html>
