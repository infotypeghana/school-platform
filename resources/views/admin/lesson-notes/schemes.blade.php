@extends('layouts.admin')

@section('title', 'Schemes of Work')
@section('page-title', 'Schemes of Work')

@section('content')
<div class="space-y-6">

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
                        <option value="{{ $c->id }}" @selected($c->id == request()->integer('class_id'))>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Teacher</label>
                <select name="teacher_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Teachers</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" @selected($t->id == request()->integer('teacher_id'))>{{ $t->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button>
            <a href="{{ route('admin.lesson-notes.schemes') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2">Reset</a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($schemes->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm">No schemes of work found.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Title / Subject</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Teacher</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Class</th>
                        <th class="px-4 py-3 text-center hidden lg:table-cell">Weeks</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($schemes as $scheme)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-900">{{ $scheme->title }}</p>
                            <p class="text-xs text-gray-400">{{ $scheme->subject?->name }}</p>
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-600">{{ $scheme->teacher?->full_name }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell text-gray-600">{{ $scheme->schoolClass?->name }}</td>
                        <td class="px-4 py-3 hidden lg:table-cell text-center text-gray-600">{{ $scheme->weeks_count ?? $scheme->weeks->count() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.lesson-notes.scheme-show', $scheme->id) }}"
                               class="text-blue-600 hover:text-blue-800 text-xs font-medium">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $schemes->links() }}
            </div>
        @endif
    </div>

    <div class="flex justify-start">
        <a href="{{ route('admin.lesson-notes.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Back to Lesson Notes</a>
    </div>
</div>
@endsection
