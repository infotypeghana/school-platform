@extends('layouts.admin')

@section('title', $schoolClass->full_name . ' — Roster')
@section('page-title', $schoolClass->full_name)

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <p class="text-sm text-gray-500">
    Class Teacher: <strong>{{ $schoolClass->classTeacher?->full_name ?? 'Not assigned' }}</strong>
    &nbsp;·&nbsp; {{ $schoolClass->students->count() }} active student(s)
    &nbsp;·&nbsp; {{ $schoolClass->subjects->count() }} subject(s)
  </p>
  <div class="flex gap-2">
    <a href="{{ route('admin.classes.subjects', $schoolClass) }}"
       class="bg-violet-600 hover:bg-violet-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
      Manage Subjects
    </a>
    <a href="{{ route('admin.classes') }}" class="px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Back</a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">#</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Name</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Admission No.</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Gender</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Guardian Phone</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($schoolClass->students as $i => $student)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
          <td class="px-4 py-3 font-medium text-gray-900">{{ $student->full_name }}</td>
          <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $student->admission_number ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-600">{{ ucfirst($student->gender ?? '—') }}</td>
          <td class="px-4 py-3 text-gray-600 text-xs">{{ $student->guardian_phone ?? '—' }}</td>
          <td class="px-4 py-3 text-right">
            <a href="{{ route('admin.students.show', $student) }}" class="text-xs text-blue-600 hover:underline">View</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No active students in this class.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
