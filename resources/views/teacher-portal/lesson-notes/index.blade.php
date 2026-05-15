@extends('layouts.teacher-portal')

@section('title', 'My Lesson Notes')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-gray-900">My Lesson Notes</h2>
        <a href="{{ route('teacher.portal.lesson-notes.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            + New Lesson Note
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
        @foreach([
            ['label' => 'Total',     'value' => $stats['total'],     'color' => 'blue'],
            ['label' => 'Draft',     'value' => $stats['draft'],     'color' => 'gray'],
            ['label' => 'Submitted', 'value' => $stats['submitted'], 'color' => 'indigo'],
            ['label' => 'Approved',  'value' => $stats['approved'],  'color' => 'green'],
            ['label' => 'Revision',  'value' => $stats['revision'],  'color' => 'amber'],
        ] as $s)
        <div class="bg-white rounded-xl border border-gray-200 px-3 py-3 text-center">
            <p class="text-xl font-bold text-{{ $s['color'] }}-600">{{ $s['value'] }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Filters --}}
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
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <select name="status" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All</option>
                    @foreach(['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'revision_requested' => 'Revision Needed'] as $val => $lbl)
                        <option value="{{ $val }}" @selected($selectedStatus === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
            <a href="{{ route('teacher.portal.lesson-notes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2">Reset</a>
        </form>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($notes->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">No lesson notes yet. Create your first one!</p>
                <a href="{{ route('teacher.portal.lesson-notes.create') }}"
                   class="mt-3 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">
                    + New Lesson Note
                </a>
            </div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($notes as $note)
                <div class="flex items-start justify-between gap-4 px-5 py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('teacher.portal.lesson-notes.show', $note->id) }}"
                                   class="font-medium text-gray-900 hover:text-blue-600 line-clamp-1">{{ $note->title }}</a>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $note->subject?->name }} · {{ $note->schoolClass?->name }}
                                    @if($note->lesson_date) · {{ $note->lesson_date->format('d M Y') }} @endif
                                    @if($note->day_of_week) · {{ $note->day_of_week }} @endif
                                </p>
                            </div>
                        </div>

                        {{-- Revision notice --}}
                        @if($note->status === 'revision_requested' && $note->revision_notes)
                        <div class="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            <p class="text-xs text-amber-800"><strong>Revision needed:</strong> {{ Str::limit($note->revision_notes, 100) }}</p>
                        </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $note->statusBadgeClass() }}">
                            {{ $note->statusLabel() }}
                        </span>
                        <a href="{{ route('teacher.portal.lesson-notes.show', $note->id) }}"
                           class="text-sm text-blue-600 hover:text-blue-800 font-medium">View</a>
                        @if(in_array($note->status, ['draft', 'revision_requested']))
                        <a href="{{ route('teacher.portal.lesson-notes.edit', $note->id) }}"
                           class="text-sm text-gray-500 hover:text-gray-700">Edit</a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $notes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
