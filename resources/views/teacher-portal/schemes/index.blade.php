@extends('layouts.teacher-portal')

@section('title', 'Schemes of Work')

@section('content')
<div class="space-y-5">

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">Schemes of Work</h2>
        <a href="{{ route('teacher.portal.schemes.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
            + New Scheme
        </a>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                <select name="term_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" @selected($t->id == $selectedTermId)>
                            {{ $t->term_name }} — {{ $t->academicYear?->year_label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
        </form>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($schemes->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm">No schemes yet. Create your termly scheme of work!</p>
            </div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($schemes as $scheme)
            @php
                $total     = $scheme->weeks->count();
                $completed = $scheme->weeks->where('is_completed', true)->count();
                $pct       = $total > 0 ? round($completed / $total * 100) : 0;
            @endphp
            <div class="px-5 py-4 hover:bg-gray-50 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('teacher.portal.schemes.show', $scheme->id) }}"
                           class="font-medium text-gray-900 hover:text-blue-600">{{ $scheme->title }}</a>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $scheme->subject?->name }} · {{ $scheme->schoolClass?->name }}</p>
                        @if($total > 0)
                        <div class="mt-2 flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-1.5 bg-green-500 rounded-full" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-xs text-gray-500 whitespace-nowrap">{{ $completed }}/{{ $total }} weeks</span>
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <a href="{{ route('teacher.portal.schemes.show', $scheme->id) }}"
                           class="text-sm text-blue-600 hover:text-blue-800 font-medium">View</a>
                        <a href="{{ route('teacher.portal.schemes.edit', $scheme->id) }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Edit</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t border-gray-100">{{ $schemes->links() }}</div>
        @endif
    </div>

</div>
@endsection
