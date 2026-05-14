@extends('layouts.superadmin')

@section('title', $term->term_name)
@section('page-title', 'Academic Term')

@section('content')

<div class="mb-5 flex items-center justify-between">
  <nav class="text-sm text-gray-500 flex items-center gap-1.5">
    <a href="{{ route('superadmin.academic-years.index') }}" class="hover:text-blue-600">Academic Years</a>
    <span>/</span>
    <span class="hover:text-blue-600 cursor-pointer">{{ $term->academicYear->year_label }}</span>
    <span>/</span>
    <span class="text-gray-800 font-medium">{{ $term->term_name }}</span>
  </nav>
  <a href="{{ route('superadmin.academic-terms.edit', $term) }}"
     class="inline-flex items-center gap-1.5 bg-white border border-gray-200 text-gray-700
            hover:border-blue-400 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
    Edit Term
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

{{-- Detail card --}}
<div class="bg-white rounded-xl border {{ $term->is_current ? 'border-emerald-300' : 'border-gray-100' }} shadow-sm p-6 mb-6">
  <div class="flex items-start justify-between">
    <div class="flex items-center gap-4">
      <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center flex-shrink-0">
        <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
        </svg>
      </div>
      <div>
        <h2 class="text-xl font-bold text-gray-900">{{ $term->term_name }}</h2>
        <p class="text-sm text-gray-500 mt-0.5">
          Term {{ $term->term_number }} &nbsp;·&nbsp; {{ $term->academicYear->year_label }}
        </p>
      </div>
    </div>
    <div>
      @if($term->is_current)
        <span class="bg-emerald-100 text-emerald-700 text-xs font-semibold px-2.5 py-1 rounded-full">
          Current Term
        </span>
      @else
        <span class="bg-gray-100 text-gray-500 text-xs font-semibold px-2.5 py-1 rounded-full">
          Inactive
        </span>
      @endif
    </div>
  </div>

  <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="bg-gray-50 rounded-lg p-4">
      <p class="text-xs text-gray-500 mb-1">Start Date</p>
      <p class="text-sm font-semibold text-gray-800">{{ $term->start_date->format('d M Y') }}</p>
    </div>
    <div class="bg-gray-50 rounded-lg p-4">
      <p class="text-xs text-gray-500 mb-1">End Date</p>
      <p class="text-sm font-semibold text-gray-800">{{ $term->end_date->format('d M Y') }}</p>
    </div>
    <div class="bg-gray-50 rounded-lg p-4">
      <p class="text-xs text-gray-500 mb-1">Duration</p>
      <p class="text-sm font-semibold text-gray-800">
        {{ $term->start_date->diffInDays($term->end_date) }} days
      </p>
    </div>
    <div class="bg-gray-50 rounded-lg p-4">
      <p class="text-xs text-gray-500 mb-1">Progress</p>
      @php
        $total = $term->start_date->diffInDays($term->end_date);
        $elapsed = min($term->start_date->diffInDays(now()), $total);
        $pct = $total > 0 ? round(($elapsed / $total) * 100) : 0;
        $pct = max(0, min(100, $pct));
      @endphp
      <p class="text-sm font-semibold text-gray-800">{{ $pct }}% complete</p>
      <div class="mt-1 h-1.5 bg-gray-200 rounded-full">
        <div class="h-1.5 bg-blue-500 rounded-full" style="width: {{ $pct }}%"></div>
      </div>
    </div>
  </div>
</div>

{{-- Actions --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
  <h3 class="text-sm font-semibold text-gray-700 mb-4">Actions</h3>
  <div class="flex flex-wrap gap-3">
    <a href="{{ route('superadmin.academic-terms.edit', $term) }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white
              px-4 py-2 rounded-lg text-sm font-medium transition-colors">
      Edit Term Dates
    </a>
    <a href="{{ route('superadmin.subscriptions.index', ['term_id' => $term->id]) }}"
       class="inline-flex items-center gap-1.5 bg-white border border-gray-200 text-gray-700
              hover:border-blue-400 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
      View Subscriptions
    </a>
  </div>
</div>

{{-- Danger zone --}}
<div class="mt-6 bg-red-50 border border-red-200 rounded-xl p-5">
  <h4 class="text-sm font-semibold text-red-700 mb-1">Danger Zone</h4>
  <p class="text-xs text-red-600 mb-3">
    Deleting this term will remove all associated subscription and attendance data.
    This action cannot be undone.
  </p>
  <form method="POST" action="{{ route('superadmin.academic-terms.destroy', $term) }}"
        onsubmit="return confirm('Permanently delete {{ $term->term_name }}?')">
    @csrf @method('DELETE')
    <button class="text-xs font-semibold text-red-600 hover:text-red-800 border border-red-300
                   hover:border-red-500 px-3 py-1.5 rounded-lg transition-colors">
      Delete This Term
    </button>
  </form>
</div>

@endsection
