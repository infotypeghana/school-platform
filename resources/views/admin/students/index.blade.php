@extends('layouts.admin')

@section('title', 'Students')
@section('page-title', 'Students')

@section('content')

{{-- Top bar --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search name or admission no."
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-blue-500">
    <select name="class_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All classes</option>
      @foreach($classes as $class)
        <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>{{ $class->full_name }}</option>
      @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All statuses</option>
      @foreach(['active','inactive','graduated','withdrawn'] as $s)
        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('admin.students.export') . '?' . http_build_query(request()->only(['class_id','status'])) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      Export
    </a>
    <a href="{{ route('admin.students.import') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
      </svg>
      Import
    </a>
    <a href="{{ route('admin.promotion') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-indigo-200 text-indigo-700 bg-indigo-50 text-sm font-medium hover:bg-indigo-100 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
      </svg>
      Promote
    </a>
    <a href="{{ route('admin.students.create') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      + Add Student
    </a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Student</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Admission No.</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Class</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Gender</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Guardian</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($students as $student)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3">
            <div class="font-medium text-gray-900">{{ $student->full_name }}</div>
            <div class="text-xs text-gray-400">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</div>
          </td>
          <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ $student->admission_number ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $student->schoolClass?->full_name ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-600">{{ ucfirst($student->gender ?? '—') }}</td>
          <td class="px-4 py-3">
            <div class="text-gray-700 text-xs">{{ $student->guardian_name ?? '—' }}</div>
            <div class="text-gray-400 text-xs">{{ $student->guardian_phone ?? '' }}</div>
          </td>
          <td class="px-4 py-3">
            @php
              $badge = match($student->status) {
                'active'    => 'bg-emerald-100 text-emerald-700',
                'inactive'  => 'bg-gray-100 text-gray-600',
                'graduated' => 'bg-blue-100 text-blue-700',
                'withdrawn' => 'bg-red-100 text-red-600',
                default     => 'bg-gray-100 text-gray-600',
              };
            @endphp
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
              {{ ucfirst($student->status) }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <a href="{{ route('admin.students.show', $student) }}"
                 class="text-xs text-blue-600 hover:underline">View</a>
              <a href="{{ route('admin.students.edit', $student) }}"
                 class="text-xs text-gray-500 hover:text-gray-700">Edit</a>
              <form method="POST" action="{{ route('admin.students.destroy', $student) }}"
                    onsubmit="return confirm('Remove {{ $student->full_name }}?')" class="inline">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No students found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  @if($students->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $students->links() }}</div>
  @endif
</div>
@endsection
