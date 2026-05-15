@extends('layouts.admin')

@section('title', 'Calendar Settings')
@section('page-title', 'Settings')

@section('content')
@include('admin.settings._nav')

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    {{ session('success') }}
  </div>
@endif

<div class="max-w-2xl space-y-5">

  {{-- Explainer --}}
  <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
    <p class="font-semibold mb-1">How the academic calendar works</p>
    <p class="text-blue-700">
      Academic years and terms are managed globally by the platform administrator.
      Here you choose <strong>which term is currently active for your school</strong> — this
      controls what term appears by default in score entry, attendance, report cards, and fees.
      If you don't set a term, the platform's global active term is used automatically.
    </p>
  </div>

  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Active Term</h3>

    <form method="POST" action="{{ route('admin.settings.calendar.update') }}">
      @csrf @method('PUT')

      <div class="space-y-3">

        {{-- "Use global" option --}}
        <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors
                      {{ is_null($currentTermId) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
          <input type="radio" name="current_term_id" value=""
                 {{ is_null($currentTermId) ? 'checked' : '' }}
                 class="mt-0.5 text-blue-600">
          <div>
            <p class="text-sm font-medium text-gray-800">Follow platform default</p>
            <p class="text-xs text-gray-500 mt-0.5">
              Automatically tracks whichever term the platform administrator marks as current.
            </p>
          </div>
        </label>

        {{-- Per-year grouped term options --}}
        @foreach($years as $year)
          @if($year->terms->isNotEmpty())
            <div>
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2 px-1">
                {{ $year->year_label }}
              </p>
              <div class="space-y-2">
                @foreach($year->terms as $term)
                  @php $selected = $currentTermId == $term->id; @endphp
                  <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors
                                {{ $selected ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300' }}">
                    <input type="radio" name="current_term_id" value="{{ $term->id }}"
                           {{ $selected ? 'checked' : '' }}
                           class="mt-0.5 text-blue-600">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-800">{{ $term->term_name }}</p>
                        @if($term->is_current)
                          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 flex-shrink-0">
                            Platform default
                          </span>
                        @endif
                      </div>
                      <p class="text-xs text-gray-500 mt-0.5">
                        {{ $term->start_date?->format('d M Y') ?? '—' }}
                        →
                        {{ $term->end_date?->format('d M Y') ?? '—' }}
                      </p>
                    </div>
                  </label>
                @endforeach
              </div>
            </div>
          @endif
        @endforeach

        @if($years->isEmpty())
          <p class="text-sm text-gray-400 py-4 text-center">
            No academic terms have been set up yet. Contact your platform administrator.
          </p>
        @endif
      </div>

      <div class="pt-5 border-t border-gray-100 mt-5">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Save Calendar Setting
        </button>
      </div>
    </form>
  </div>

  {{-- Current state summary --}}
  @php
    $resolvedTerm = $tenant->resolveCurrentTerm();
  @endphp
  @if($resolvedTerm)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Currently Active</h3>
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm font-semibold text-gray-800">
            {{ $resolvedTerm->term_name }}
            <span class="font-normal text-gray-500">· {{ $resolvedTerm->academicYear?->year_label ?? '—' }}</span>
          </p>
          <p class="text-xs text-gray-400 mt-0.5">
            {{ $resolvedTerm->start_date?->format('d M Y') }} – {{ $resolvedTerm->end_date?->format('d M Y') }}
            @if(is_null($currentTermId))
              · <em>following platform default</em>
            @else
              · <em>school-specific override</em>
            @endif
          </p>
        </div>
        @php
          $today = \Carbon\Carbon::today();
          if ($resolvedTerm->start_date && $resolvedTerm->end_date) {
            if ($today->lt($resolvedTerm->start_date))      { $phase = ['Upcoming',    'bg-blue-100 text-blue-700']; }
            elseif ($today->lte($resolvedTerm->end_date))   { $phase = ['In Progress', 'bg-emerald-100 text-emerald-700']; }
            else                                             { $phase = ['Ended',       'bg-gray-100 text-gray-600']; }
          } else { $phase = ['Unknown', 'bg-gray-100 text-gray-500']; }
        @endphp
        <span class="inline-flex px-3 py-1 rounded-full text-xs font-semibold {{ $phase[1] }}">
          {{ $phase[0] }}
        </span>
      </div>
    </div>
  @endif

</div>
@endsection
