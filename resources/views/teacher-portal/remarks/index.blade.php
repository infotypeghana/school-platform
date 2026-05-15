@extends('layouts.teacher-portal')
@section('title', 'Student Remarks')

@section('content')

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div>
        <h1 class="text-lg font-bold text-gray-900">Student Remarks</h1>
        <p class="text-sm text-gray-500 mt-0.5">Enter class teacher remarks and conduct ratings for each student.</p>
    </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

{{-- Class + Term selector --}}
<form method="GET" action="{{ route('teacher.portal.remarks') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5">
    <div class="flex flex-wrap gap-3 items-end">
        @if($myClasses->count() > 1)
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
            <select name="class_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @foreach($myClasses as $cls)
                    <option value="{{ $cls->id }}" {{ $cls->id === $class->id ? 'selected' : '' }}>
                        {{ $cls->full_name }}
                    </option>
                @endforeach
            </select>
        </div>
        @else
            <input type="hidden" name="class_id" value="{{ $class->id }}">
        @endif

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
            <select name="term_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @foreach($terms as $t)
                    <option value="{{ $t->id }}" {{ $t->id === $term->id ? 'selected' : '' }}>
                        {{ $t->term_name }} · {{ $t->academicYear?->year_label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</form>

{{-- Student list --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
        <span class="text-sm font-semibold text-gray-700">
            {{ $class->full_name }} — {{ $term->term_name }}
        </span>
        <span class="text-xs text-gray-400">{{ $students->count() }} student(s)</span>
    </div>

    @if($students->isEmpty())
        <p class="px-5 py-8 text-sm text-gray-400 text-center">No active students in this class.</p>
    @else
        <table class="w-full text-sm">
            <thead class="border-b border-gray-100">
                <tr class="text-xs font-semibold text-gray-500">
                    <th class="px-5 py-3 text-left">#</th>
                    <th class="px-5 py-3 text-left">Student</th>
                    <th class="px-5 py-3 text-left">Remark</th>
                    <th class="px-5 py-3 text-left">Conduct</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($students as $i => $student)
                    @php $rc = $reportCards->get($student->id); @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-gray-400 text-xs">{{ $i + 1 }}</td>
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-800">{{ $student->full_name }}</p>
                            <p class="text-xs text-gray-400">{{ $student->admission_number }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if($rc?->class_teacher_remark)
                                <p class="text-xs text-gray-600 line-clamp-2 max-w-[200px]">
                                    {{ $rc->class_teacher_remark }}
                                </p>
                            @else
                                <span class="text-xs text-gray-300 italic">Not entered</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            @if($rc?->conduct_ratings)
                                @php
                                  $filled = count(array_filter($rc->conduct_ratings));
                                  $total  = count(\App\Models\ReportCard::CONDUCT_TRAITS);
                                @endphp
                                <span class="text-xs {{ $filled === $total ? 'text-emerald-600' : 'text-amber-600' }} font-medium">
                                    {{ $filled }}/{{ $total }} traits rated
                                </span>
                            @else
                                <span class="text-xs text-gray-300 italic">Not rated</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('teacher.portal.remarks.edit', ['student_id' => $student->id, 'term_id' => $term->id]) }}"
                               class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium border border-blue-200 hover:border-blue-400 px-3 py-1.5 rounded-lg transition-colors">
                                {{ $rc?->class_teacher_remark ? 'Edit' : 'Enter' }} Remarks
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
