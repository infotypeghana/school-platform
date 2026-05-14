@extends('layouts.admin')

@section('title', 'Report Cards')
@section('page-title', 'Report Cards')

@section('content')
<div class="max-w-2xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <p class="text-sm text-gray-500 mb-6">Select a class and term to manage, generate, or download report cards.</p>

    <form method="GET" action="{{ route('admin.report-cards.class') }}" class="space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
        <select name="class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select a class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}">{{ $class->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
        <select name="term_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select a term —</option>
          @foreach($terms as $term)
            <option value="{{ $term->id }}" {{ $current?->id === $term->id ? 'selected' : '' }}>
              {{ $term->term_name }} — {{ $term->academicYear?->year_label }}
            </option>
          @endforeach
        </select>
      </div>
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">
        Open Class Report Cards →
      </button>
    </form>
  </div>
</div>
@endsection
