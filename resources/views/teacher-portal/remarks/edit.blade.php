@extends('layouts.teacher-portal')
@section('title', 'Remarks — ' . $student->full_name)

@push('head')
<style>
  .conduct-label { cursor: pointer; }
  .conduct-label input[type="radio"] { display: none; }
</style>
@endpush

@section('content')

{{-- Breadcrumb --}}
<div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
    <a href="{{ route('teacher.portal.remarks', ['class_id' => $student->school_class_id, 'term_id' => $term->id]) }}"
       class="hover:text-blue-600">Remarks</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">{{ $student->full_name }}</span>
</div>

<div class="max-w-xl">

  {{-- Student header --}}
  <div class="mb-4">
    <h1 class="text-lg font-bold text-gray-900">{{ $student->full_name }}</h1>
    <p class="text-sm text-gray-500">
        {{ $student->schoolClass?->full_name }} · {{ $term->term_name }} · {{ $term->academicYear?->year_label }}
    </p>
  </div>

  @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('teacher.portal.remarks.update') }}" class="space-y-5">
    @csrf @method('PUT')
    <input type="hidden" name="student_id" value="{{ $student->id }}">
    <input type="hidden" name="term_id"    value="{{ $term->id }}">

    {{-- ── Class Teacher Remark ─────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <label class="block text-sm font-semibold text-gray-700 mb-2">Your Remark (Class Teacher)</label>
      <textarea name="class_teacher_remark" rows="5"
                placeholder="e.g. {{ $student->first_name }} is a hardworking student who participates actively in class..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('class_teacher_remark', $reportCard->class_teacher_remark) }}</textarea>
      <p class="text-xs text-gray-400 mt-1">Max 1,000 characters. This will print on the report card.</p>
    </div>

    {{-- ── Conduct & Behaviour ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-1">Conduct &amp; Behaviour</h3>
      <p class="text-xs text-gray-400 mb-4">Tap a rating for each trait.</p>

      <div class="space-y-4">
        @foreach(\App\Models\ReportCard::CONDUCT_TRAITS as $key => $label)
          @php $current = old("conduct.$key", $reportCard->conduct_ratings[$key] ?? ''); @endphp
          <div>
            <p class="text-xs font-semibold text-gray-600 mb-1.5">{{ $label }}</p>
            <div class="flex flex-wrap gap-2" id="group-{{ $key }}">
              @foreach(\App\Models\ReportCard::CONDUCT_RATINGS as $rating)
                @php
                  $sel = $current === $rating;
                  $active = match($rating) {
                    'Excellent' => 'bg-emerald-600 text-white border-emerald-600',
                    'Very Good' => 'bg-blue-600 text-white border-blue-600',
                    'Good'      => 'bg-amber-500 text-white border-amber-500',
                    'Fair'      => 'bg-orange-500 text-white border-orange-500',
                    'Poor'      => 'bg-red-600 text-white border-red-600',
                    default     => '',
                  };
                  $inactive = 'bg-white border-gray-200 text-gray-600 hover:border-gray-400';
                @endphp
                <label class="conduct-label">
                  <input type="radio" name="conduct[{{ $key }}]" value="{{ $rating }}"
                         {{ $sel ? 'checked' : '' }}
                         onchange="updatePill(this)">
                  <span class="inline-block border rounded-full px-3 py-1 text-xs font-medium transition-all
                               {{ $sel ? $active : $inactive }}">
                    {{ $rating }}
                  </span>
                </label>
              @endforeach
              {{-- Clear --}}
              <label class="conduct-label">
                <input type="radio" name="conduct[{{ $key }}]" value=""
                       {{ $current === '' ? 'checked' : '' }}
                       onchange="updatePill(this)">
                <span class="inline-block border border-dashed border-gray-300 rounded-full px-3 py-1 text-xs text-gray-400 hover:text-gray-600 transition-all">
                  Not set
                </span>
              </label>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="flex gap-3">
      <button type="submit"
              class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
        Save Remarks
      </button>
      <a href="{{ route('teacher.portal.remarks', ['class_id' => $student->school_class_id, 'term_id' => $term->id]) }}"
         class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
        Back
      </a>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
const COLOR_MAP = {
  'Excellent': 'bg-emerald-600 text-white border-emerald-600',
  'Very Good': 'bg-blue-600 text-white border-blue-600',
  'Good':      'bg-amber-500 text-white border-amber-500',
  'Fair':      'bg-orange-500 text-white border-orange-500',
  'Poor':      'bg-red-600 text-white border-red-600',
};
const INACTIVE = 'bg-white border-gray-200 text-gray-600 hover:border-gray-400';
const CLEAR    = 'border-dashed border-gray-300 text-gray-400 hover:text-gray-600';

function updatePill(radio) {
  const group = radio.closest('.flex');
  group.querySelectorAll('input[type="radio"]').forEach(r => {
    const span = r.nextElementSibling;
    if (!span) return;
    // Reset
    span.className = span.className
      .replace(/bg-\S+/g, '').replace(/text-white/g, '').replace(/border-\S+/g, '')
      .trim();
    if (r.value === '') {
      span.className += ' border ' + CLEAR;
    } else {
      span.className += ' border ' + INACTIVE;
    }
  });
  // Highlight selected
  const sel = radio.nextElementSibling;
  if (sel && radio.value !== '') {
    sel.className = sel.className
      .replace(/bg-\S+/g, '').replace(/text-white/g, '').replace(/border-\S+/g, '')
      .trim() + ' border ' + (COLOR_MAP[radio.value] || INACTIVE);
  }
}
</script>
@endpush
