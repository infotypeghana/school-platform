@extends('layouts.admin')

@section('title', $schoolClass->full_name . ' — Subjects')
@section('page-title', 'Manage Subjects')

@section('content')

<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $schoolClass->full_name }}</h2>
    <p class="text-sm text-gray-500">{{ $schoolClass->subjects->count() }} subject(s) assigned</p>
  </div>
  <a href="{{ route('admin.classes.show', $schoolClass) }}" class="text-sm text-blue-600 hover:underline">← Back to roster</a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  {{-- Add subject form --}}
  <div class="lg:col-span-1">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-4">Add Subject</h3>
      <form method="POST" action="{{ route('admin.classes.subjects.store', $schoolClass) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name *</label>
          <input type="text" name="name" required placeholder="e.g. Mathematics"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-center gap-2">
          <input type="checkbox" name="is_core" id="is_core" value="1"
                 class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
          <label for="is_core" class="text-sm text-gray-700">Core subject</label>
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition-colors text-sm">
          Add Subject
        </button>
      </form>
    </div>
  </div>

  {{-- Subject list --}}
  <div class="lg:col-span-2">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">#</th>
            <th class="px-4 py-3 text-left font-semibold text-gray-600">Subject</th>
            <th class="px-4 py-3 text-center font-semibold text-gray-600">Type</th>
            <th class="px-4 py-3 text-right font-semibold text-gray-600">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($schoolClass->subjects as $i => $subject)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
              <td class="px-4 py-3 font-medium text-gray-900">{{ $subject->name }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                  {{ $subject->is_core ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }}">
                  {{ $subject->is_core ? 'Core' : 'Elective' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <form method="POST"
                      action="{{ route('admin.classes.subjects.destroy', [$schoolClass, $subject]) }}"
                      onsubmit="return confirm('Remove {{ $subject->name }}?')" class="inline">
                  @csrf @method('DELETE')
                  <button class="text-xs text-red-400 hover:text-red-600">Remove</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">No subjects yet. Add one above.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
