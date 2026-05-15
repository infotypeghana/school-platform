@extends('layouts.admin')

@section('title', 'Lesson Notes')
@section('page-title', 'Lesson Notes')

@section('content')
<div class="space-y-6">

    {{-- Stats strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach([
            ['label' => 'Total',    'value' => $stats['total'],     'color' => 'blue'],
            ['label' => 'Draft',    'value' => $stats['draft'],     'color' => 'gray'],
            ['label' => 'Submitted','value' => $stats['submitted'], 'color' => 'indigo'],
            ['label' => 'Approved', 'value' => $stats['approved'],  'color' => 'green'],
            ['label' => 'Revision', 'value' => $stats['revision'],  'color' => 'amber'],
        ] as $s)
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-{{ $s['color'] }}-600">{{ $s['value'] }}</p>
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
                <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                <select name="class_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Classes</option>
                    @foreach($classes as $c)
                        <option value="{{ $c->id }}" @selected($c->id == $selectedClassId)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Teacher</label>
                <select name="teacher_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Teachers</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($t->id == $selectedTeacherId)>{{ $t->full_name }}</option>
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
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                <select name="type" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Types</option>
                    <option value="lesson_note" @selected($selectedType === 'lesson_note')>Lesson Note</option>
                    <option value="lesson_plan" @selected($selectedType === 'lesson_plan')>Lesson Plan</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
            <a href="{{ route('admin.lesson-notes.index') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2">Reset</a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($notes->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">No lesson notes found for the selected filters.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Title / Subject</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Teacher</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Class</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Date</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($notes as $note)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $note->title }}</p>
                            <p class="text-xs text-gray-400">{{ $note->subject?->name }} · {{ ucfirst(str_replace('_', ' ', $note->type)) }}</p>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-600">{{ $note->teacher?->full_name }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell text-gray-600">{{ $note->schoolClass?->name }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell text-gray-500">{{ $note->lesson_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $note->statusBadgeClass() }}">
                                {{ $note->statusLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.lesson-notes.show', $note->id) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $notes->links() }}
            </div>
        @endif
    </div>

    {{-- Schemes of work link --}}
    <div class="flex justify-end">
        <a href="{{ route('admin.lesson-notes.schemes') }}"
           class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            View Schemes of Work →
        </a>
    </div>

</div>
@endsection
