@extends('layouts.teacher-portal')

@section('title', $scheme->title)

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    <div class="flex items-center justify-between">
        <a href="{{ route('teacher.portal.schemes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Schemes</a>
        <div class="flex items-center gap-3">
            <a href="{{ route('teacher.portal.schemes.edit', $scheme->id) }}"
               class="text-sm text-gray-600 hover:text-gray-900 font-medium">Edit</a>
            <button onclick="window.print()" class="text-sm text-gray-400 hover:text-gray-600" title="Print">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-xl font-bold text-gray-900">{{ $scheme->title }}</h2>
        @if($scheme->description)<p class="text-sm text-gray-500 mt-1">{{ $scheme->description }}</p>@endif
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-sm">
            <div><p class="text-xs text-gray-400">Subject</p><p class="font-medium">{{ $scheme->subject?->name }}</p></div>
            <div><p class="text-xs text-gray-400">Class</p><p class="font-medium">{{ $scheme->schoolClass?->name }}</p></div>
            <div><p class="text-xs text-gray-400">Term</p><p class="font-medium">{{ $scheme->term?->term_name }}</p></div>
            <div><p class="text-xs text-gray-400">Year</p><p class="font-medium">{{ $scheme->term?->academicYear?->year_label }}</p></div>
        </div>
    </div>

    {{-- Progress --}}
    @php
        $totalWeeks = $scheme->weeks->count();
        $completed  = $scheme->weeks->where('is_completed', true)->count();
        $pct        = $totalWeeks > 0 ? round($completed / $totalWeeks * 100) : 0;
    @endphp
    @if($totalWeeks > 0)
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between text-sm mb-2">
            <span class="font-medium text-gray-700">Progress</span>
            <span class="text-gray-500">{{ $completed }} / {{ $totalWeeks }} weeks ({{ $pct }}%)</span>
        </div>
        <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-2 bg-green-500 rounded-full" style="width: {{ $pct }}%"></div>
        </div>
    </div>
    @endif

    {{-- Weekly plan --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Weekly Plan</h3>
            <span class="text-xs text-gray-400">{{ $totalWeeks }} week(s)</span>
        </div>
        @if($scheme->weeks->isEmpty())
            <p class="text-center py-8 text-sm text-gray-400">No weeks defined. Edit to add weeks.</p>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($scheme->weeks as $week)
            <div class="px-5 py-4 {{ $week->is_completed ? 'bg-green-50' : '' }}">
                <div class="flex items-start gap-3">
                    <span class="flex-shrink-0 h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold mt-0.5
                                 {{ $week->is_completed ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                        {{ $week->week_number }}
                    </span>
                    <div class="flex-1 space-y-1">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium text-gray-900 text-sm">{{ $week->topic }}</p>
                            @if($week->is_completed)
                                <span class="text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full flex-shrink-0">Done</span>
                            @endif
                        </div>
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
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Delete --}}
    <div class="flex justify-end pb-4">
        <form method="POST" action="{{ route('teacher.portal.schemes.destroy', $scheme->id) }}"
              onsubmit="return confirm('Delete this scheme of work?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete scheme</button>
        </form>
    </div>

</div>
@endsection

@push('head')
<style>
@media print {
    nav, .no-print { display: none !important; }
    body { background: white; }
}
</style>
@endpush
