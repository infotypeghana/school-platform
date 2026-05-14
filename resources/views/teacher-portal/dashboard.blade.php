@use('Illuminate\Support\Str')
@extends('layouts.teacher-portal')
@section('title', 'Dashboard')

@section('content')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $teacher->first_name }}!</h1>
    <p class="text-sm text-gray-500 mt-0.5">
        @if($currentTerm)
            {{ $currentTerm->term_name }} · {{ $currentTerm->academicYear?->year_label }}
        @else
            No active term
        @endif
    </p>
</div>

{{-- Quick stats --}}
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">My Classes</p>
        <p class="text-2xl font-bold text-gray-900">{{ $myClasses->count() }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1">Total Students</p>
        <p class="text-2xl font-bold text-gray-900">{{ $myClasses->sum(fn ($c) => $c->students->count()) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 col-span-2 sm:col-span-1">
        <p class="text-xs text-gray-500 mb-1">Last Login</p>
        <p class="text-sm font-semibold text-gray-900">
            {{ $teacher->portal_last_login ? $teacher->portal_last_login->diffForHumans() : 'First login' }}
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

    {{-- My Classes --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">My Classes</h2>
            <a href="{{ route('teacher.portal.scores') }}"
               class="text-xs text-blue-600 hover:text-blue-700 font-medium">Enter Scores →</a>
        </div>
        @if($myClasses->isEmpty())
            <p class="px-5 py-4 text-sm text-gray-400">No classes assigned yet.</p>
        @else
            <ul class="divide-y divide-gray-50">
                @foreach($myClasses as $class)
                    <li class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $class->full_name ?? $class->name }}</p>
                            @if($class->level)
                                <p class="text-xs text-gray-400">{{ $class->level }}</p>
                            @endif
                        </div>
                        <span class="text-xs text-gray-500">
                            {{ $class->students->count() }} student{{ $class->students->count() === 1 ? '' : 's' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Announcements --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Recent Announcements</h2>
        </div>
        @if($announcements->isEmpty())
            <p class="px-5 py-4 text-sm text-gray-400">No announcements.</p>
        @else
            <ul class="divide-y divide-gray-50">
                @foreach($announcements as $ann)
                    <li class="px-5 py-3">
                        <p class="text-sm font-medium text-gray-800">{{ $ann->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $ann->published_at?->format('d M Y') ?? '—' }}
                            @if($ann->audience && $ann->audience !== 'all')
                                · <span class="capitalize">{{ $ann->audience }}</span>
                            @endif
                        </p>
                        @if($ann->body)
                            <p class="text-xs text-gray-600 mt-1 line-clamp-2">
                                {{ Str::limit(strip_tags($ann->body), 120) }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>

{{-- Quick action cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5">
    <a href="{{ route('teacher.portal.scores') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl p-5 flex items-center gap-4 transition-colors group">
        <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-sm">Enter Scores</p>
            <p class="text-xs text-blue-200">CA &amp; exam marks</p>
        </div>
    </a>

    <a href="{{ route('teacher.portal.timetable') }}"
       class="bg-white border border-gray-200 hover:border-blue-200 hover:bg-blue-50 rounded-xl p-5 flex items-center gap-4 transition-colors">
        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <p class="font-semibold text-sm text-gray-900">Timetable</p>
            <p class="text-xs text-gray-400">My schedule</p>
        </div>
    </a>

    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <p class="text-xs text-gray-500 mb-1">Staff ID</p>
        <p class="font-semibold text-gray-900">{{ $teacher->staff_id ?? 'Not assigned' }}</p>
        <p class="text-xs text-gray-400 mt-1">{{ $teacher->specialization ?? $teacher->qualification ?? '—' }}</p>
    </div>
</div>

@endsection
