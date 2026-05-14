@extends('layouts.superadmin')

@section('title', 'Academic Years')
@section('page-title', 'Academic Years')

@section('content')

<div class="flex items-center justify-between mb-5">
  <p class="text-sm text-gray-500">{{ $years->count() }} academic year(s) configured</p>
  <a href="{{ route('superadmin.academic-years.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
    + Add Year
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="space-y-4">
  @forelse($years as $year)
    <div class="bg-white rounded-xl border {{ $year->is_current ? 'border-blue-300 shadow-blue-100 shadow-md' : 'border-gray-100' }} shadow-sm p-5">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
          <h3 class="font-bold text-gray-900 text-lg">{{ $year->year_label }}</h3>
          @if($year->is_current)
            <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">Current Year</span>
          @endif
        </div>
        <div class="flex gap-2">
          <a href="{{ route('superadmin.academic-years.edit', $year) }}"
             class="text-xs text-blue-600 hover:underline">Edit</a>
          <form method="POST" action="{{ route('superadmin.academic-years.destroy', $year) }}"
                onsubmit="return confirm('Delete {{ $year->year_label }}?')" class="inline">
            @csrf @method('DELETE')
            <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
          </form>
        </div>
      </div>
      <div class="flex gap-3">
        @foreach($year->terms->sortBy('term_number') as $term)
          <div class="bg-gray-50 rounded-lg px-4 py-2 text-xs text-center">
            <div class="font-semibold text-gray-700">{{ $term->term_name }}</div>
            <div class="text-gray-400 mt-0.5">
              {{ $term->start_date->format('d M') }} – {{ $term->end_date->format('d M Y') }}
            </div>
            @if($term->is_current)
              <span class="mt-1 inline-block bg-emerald-100 text-emerald-700 text-xs px-1.5 py-0.5 rounded-full">Current</span>
            @endif
          </div>
        @endforeach
        @if($year->terms->isEmpty())
          <span class="text-xs text-gray-400">No terms added yet.</span>
        @endif
      </div>
    </div>
  @empty
    <div class="text-center py-10 text-gray-400 text-sm">No academic years. <a href="{{ route('superadmin.academic-years.create') }}" class="text-blue-600 hover:underline">Create one →</a></div>
  @endforelse
</div>
@endsection
