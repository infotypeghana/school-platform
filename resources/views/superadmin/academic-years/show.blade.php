@extends('layouts.superadmin')

@section('title', $year->year_label)
@section('page-title', 'Academic Year')

@section('content')

<div class="mb-5 flex items-center justify-between">
  <nav class="text-sm text-gray-500 flex items-center gap-1.5">
    <a href="{{ route('superadmin.academic-years.index') }}" class="hover:text-blue-600">Academic Years</a>
    <span>/</span>
    <span class="text-gray-800 font-medium">{{ $year->year_label }}</span>
  </nav>
  <div class="flex gap-2">
    <a href="{{ route('superadmin.academic-years.edit', $year) }}"
       class="inline-flex items-center gap-1.5 bg-white border border-gray-200 text-gray-700
              hover:border-blue-400 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
      Edit Year
    </a>
    <a href="{{ route('superadmin.academic-terms.create') }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white
              px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
      + Add Term
    </a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

{{-- Year header card --}}
<div class="bg-white rounded-xl border {{ $year->is_current ? 'border-blue-300' : 'border-gray-100' }} shadow-sm p-6 mb-6">
  <div class="flex items-center gap-3">
    <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center">
      <svg class="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <div>
      <h2 class="text-xl font-bold text-gray-900">{{ $year->year_label }}</h2>
      <div class="flex items-center gap-2 mt-1">
        @if($year->is_current)
          <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">Current Year</span>
        @else
          <span class="bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-0.5 rounded-full">Past Year</span>
        @endif
        <span class="text-xs text-gray-400">{{ $year->terms->count() }} term(s)</span>
      </div>
    </div>
  </div>
</div>

{{-- Terms --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
    <h3 class="text-sm font-semibold text-gray-700">Terms</h3>
    <span class="text-xs text-gray-400">{{ $year->terms->count() }} term(s)</span>
  </div>

  @if($year->terms->isEmpty())
    <div class="px-6 py-12 text-center text-gray-400 text-sm">
      No terms added for this year yet.
      <a href="{{ route('superadmin.academic-terms.create') }}" class="text-blue-600 hover:underline">Add a term →</a>
    </div>
  @else
    <div class="divide-y divide-gray-50">
      @foreach($year->terms->sortBy('term_number') as $term)
        <div class="px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0
                        text-sm font-bold text-gray-500">
              {{ $term->term_number }}
            </div>
            <div>
              <p class="text-sm font-medium text-gray-800">
                {{ $term->term_name }}
                @if($term->is_current)
                  <span class="ml-2 bg-emerald-100 text-emerald-700 text-xs px-1.5 py-0.5 rounded-full font-semibold">
                    Current
                  </span>
                @endif
              </p>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ $term->start_date->format('d M Y') }} — {{ $term->end_date->format('d M Y') }}
                &nbsp;·&nbsp;
                {{ $term->start_date->diffInDays($term->end_date) }} days
              </p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <a href="{{ route('superadmin.academic-terms.edit', $term) }}"
               class="text-xs text-blue-600 hover:underline">Edit</a>
            <form method="POST" action="{{ route('superadmin.academic-terms.destroy', $term) }}"
                  onsubmit="return confirm('Delete {{ $term->term_name }}?')" class="inline">
              @csrf @method('DELETE')
              <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

{{-- Danger zone --}}
<div class="mt-6 bg-red-50 border border-red-200 rounded-xl p-5">
  <h4 class="text-sm font-semibold text-red-700 mb-1">Danger Zone</h4>
  <p class="text-xs text-red-600 mb-3">
    Deleting this academic year will also remove all associated terms and subscription records.
    This action cannot be undone.
  </p>
  <form method="POST" action="{{ route('superadmin.academic-years.destroy', $year) }}"
        onsubmit="return confirm('Permanently delete {{ $year->year_label }} and all its terms?')">
    @csrf @method('DELETE')
    <button class="text-xs font-semibold text-red-600 hover:text-red-800 border border-red-300
                   hover:border-red-500 px-3 py-1.5 rounded-lg transition-colors">
      Delete This Year
    </button>
  </form>
</div>

@endsection
