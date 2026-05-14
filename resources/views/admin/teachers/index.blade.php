@extends('layouts.admin')

@section('title', 'Teachers')
@section('page-title', 'Teachers')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search name or staff ID"
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:ring-2 focus:ring-blue-500">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All statuses</option>
      <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
      <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <div class="flex gap-2">
    <a href="{{ route('admin.teachers.import') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l4-4m0 0l4 4m-4-4v12"/>
      </svg>
      Import
    </a>
    <a href="{{ route('admin.teachers.create') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      + Add Teacher
    </a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Teacher</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Staff ID</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Contact</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Specialization</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($teachers as $teacher)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3">
            <div class="font-medium text-gray-900">{{ $teacher->full_name }}</div>
            <div class="text-xs text-gray-400">{{ $teacher->qualification ?? '—' }}</div>
          </td>
          <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $teacher->staff_id ?? '—' }}</td>
          <td class="px-4 py-3 text-xs text-gray-600">
            <div>{{ $teacher->phone ?? '—' }}</div>
            <div class="text-gray-400">{{ $teacher->email ?? '' }}</div>
          </td>
          <td class="px-4 py-3 text-gray-600 text-xs">{{ $teacher->specialization ?? '—' }}</td>
          <td class="px-4 py-3">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
              {{ $teacher->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
              {{ ucfirst($teacher->status) }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <a href="{{ route('admin.teachers.show', $teacher) }}" class="text-xs text-gray-500 hover:underline">View</a>
              <a href="{{ route('admin.teachers.edit', $teacher) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}"
                    onsubmit="return confirm('Remove {{ $teacher->full_name }}?')" class="inline">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No teachers found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  @if($teachers->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $teachers->links() }}</div>
  @endif
</div>
@endsection
