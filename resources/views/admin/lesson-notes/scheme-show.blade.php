@extends('layouts.admin')

@section('title', $scheme->title)
@section('page-title', 'Scheme of Work')

@section('content')
<div class="space-y-6 max-w-4xl">

    <div class="flex items-center justify-between">
        <a href="{{ route('admin.lesson-notes.schemes') }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Back to Schemes</a>
    </div>

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1">{{ $scheme->title }}</h2>
        @if($scheme->description)
            <p class="text-sm text-gray-500 mb-4">{{ $scheme->description }}</p>
        @endif
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Teacher</p>
                <p class="text-gray-800 mt-0.5">{{ $scheme->teacher?->full_name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Class</p>
                <p class="text-gray-800 mt-0.5">{{ $scheme->schoolClass?->name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Subject</p>
                <p class="text-gray-800 mt-0.5">{{ $scheme->subject?->name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Term</p>
                <p class="text-gray-800 mt-0.5">{{ $scheme->term?->term_name }} — {{ $scheme->term?->academicYear?->year_label }}</p>
            </div>
        </div>
    </div>

    {{-- Progress bar --}}
    @php
        $totalWeeks = $scheme->weeks->count();
        $completed  = $scheme->weeks->where('is_completed', true)->count();
        $pct        = $totalWeeks > 0 ? round($completed / $totalWeeks * 100) : 0;
    @endphp
    @if($totalWeeks > 0)
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="text-gray-600 font-medium">Progress</span>
            <span class="text-gray-500">{{ $completed }} / {{ $totalWeeks }} weeks completed ({{ $pct }}%)</span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-2 bg-green-500 rounded-full transition-all" style="width: {{ $pct }}%"></div>
        </div>
    </div>
    @endif

    {{-- Weekly breakdown --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Weekly Breakdown</h3>
        </div>
        @if($scheme->weeks->isEmpty())
            <p class="text-center py-8 text-sm text-gray-400">No weeks defined for this scheme.</p>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($scheme->weeks as $week)
            <div class="px-6 py-4 {{ $week->is_completed ? 'bg-green-50' : '' }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex-shrink-0 h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold
                                     {{ $week->is_completed ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                            {{ $week->week_number }}
                        </span>
                        <div class="space-y-1">
                            <p class="font-medium text-gray-900 text-sm">{{ $week->topic }}</p>
                            @if($week->week_ending)
                                <p class="text-xs text-gray-400">Week ending: {{ $week->week_ending->format('d M Y') }}</p>
                            @endif
                            @if($week->learning_objectives)
                                <p class="text-sm text-gray-600">{{ $week->learning_objectives }}</p>
                            @endif
                            @if($week->competencies)
                                <p class="text-xs text-gray-400">Competencies: {{ $week->competencies }}</p>
                            @endif
                            @if($week->reference)
                                <p class="text-xs text-gray-400">Reference: {{ $week->reference }}</p>
                            @endif
                        </div>
                    </div>
                    @if($week->is_completed)
                        <span class="flex-shrink-0 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">Done</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
