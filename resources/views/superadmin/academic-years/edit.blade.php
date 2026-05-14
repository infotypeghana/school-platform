@extends('layouts.superadmin')

@section('title', 'Edit Academic Year')
@section('page-title', 'Edit Academic Year')

@section('content')
<div class="max-w-sm">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if(session('success'))
      <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    <form method="POST" action="{{ route('superadmin.academic-years.update', $year) }}" class="space-y-4">
      @csrf @method('PUT')
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Year Label *</label>
        <input type="text" name="year_label" value="{{ old('year_label', $year->year_label) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_current" id="is_current" value="1"
               {{ $year->is_current ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="is_current" class="text-sm text-gray-700">Set as current academic year</label>
      </div>
      <p class="text-xs text-amber-600">⚠ Changing the current year affects which term is active platform-wide.</p>
      <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save</button>
        <a href="{{ route('superadmin.academic-years.index') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
