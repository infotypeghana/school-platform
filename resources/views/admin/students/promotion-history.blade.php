@extends('layouts.admin')

@section('title', 'Promotion History')
@section('page-title', 'Promotion History')

@section('content')
<div class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.promotion') }}"
           class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            ← Promotion
        </a>
        <h2 class="text-base font-semibold text-gray-800">All promotion records</h2>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Academic Year</label>
                <select name="academic_year_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Years</option>
                    @foreach($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($year->id == request()->integer('academic_year_id'))>
                            {{ $year->year_label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">From Class</label>
                <select name="from_class_id" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}" @selected($class->id == request()->integer('from_class_id'))>
                            {{ $class->full_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Action</label>
                <select name="action" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">All Actions</option>
                    @foreach(['promoted' => 'Promoted', 'held_back' => 'Held Back', 'graduated' => 'Graduated', 'transferred' => 'Transferred'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(request()->input('action') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">
                Filter
            </button>
            <a href="{{ route('admin.promotion.history') }}" class="text-sm text-gray-500 hover:text-gray-700 py-2">
                Reset
            </a>
        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        @if($promotions->isEmpty())
            <div class="text-center py-14 text-gray-400">
                <svg class="h-10 w-10 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <p class="text-sm">No promotion records found.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Student</th>
                        <th class="px-4 py-3 text-left hidden md:table-cell">Academic Year</th>
                        <th class="px-4 py-3 text-left">Movement</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Processed By</th>
                        <th class="px-4 py-3 text-left hidden lg:table-cell">Date</th>
                        <th class="px-4 py-3 text-left hidden xl:table-cell">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($promotions as $p)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            @if($p->student)
                                <a href="{{ route('admin.students.show', $p->student) }}"
                                   class="font-medium text-gray-900 hover:text-blue-600">
                                    {{ $p->student->full_name }}
                                </a>
                                <p class="text-xs text-gray-400 font-mono">{{ $p->student->admission_number }}</p>
                            @else
                                <span class="text-gray-400 italic">Deleted student</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 hidden md:table-cell text-gray-600">
                            {{ $p->academicYear?->year_label ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 text-xs">{{ $p->movementLabel() }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $p->actionBadgeClass() }}">
                                {{ $p->actionLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell text-gray-500 text-xs">
                            {{ $p->promotedBy?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell text-gray-400 text-xs">
                            {{ $p->created_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3 hidden xl:table-cell text-gray-500 text-xs max-w-48 truncate">
                            {{ $p->notes ?? '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-gray-100">
                {{ $promotions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
