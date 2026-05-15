@extends('layouts.admin')

@section('title', 'Remarks — ' . $reportCard->student?->full_name)
@section('page-title', 'Edit Remarks')

@section('content')
<div class="max-w-2xl">
  <div class="mb-4">
    <h2 class="text-lg font-semibold text-gray-900">{{ $reportCard->student?->full_name }}</h2>
    <p class="text-sm text-gray-500">
      {{ $reportCard->schoolClass?->full_name }} ·
      {{ $reportCard->term?->term_name }} ·
      {{ $reportCard->term?->academicYear?->year_label }}
    </p>
  </div>

  @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
      <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.report-cards.remarks.update', $reportCard) }}" class="space-y-5">
    @csrf @method('PUT')

    {{-- ── Remarks ──────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
      <h3 class="text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2">Written Remarks</h3>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Class Teacher's Remark</label>
        <textarea name="class_teacher_remark" rows="4"
                  placeholder="e.g. A hardworking student who consistently performs above expectations..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('class_teacher_remark', $reportCard->class_teacher_remark) }}</textarea>
      </div>

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Headmaster's Remark</label>
        <textarea name="headmaster_remark" rows="4"
                  placeholder="e.g. Keep up the excellent work. We are proud of your achievements..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('headmaster_remark', $reportCard->headmaster_remark) }}</textarea>
      </div>
    </div>

    {{-- ── Conduct & Behaviour ──────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
      <h3 class="text-sm font-semibold text-gray-700 border-b border-gray-100 pb-2 mb-4">Conduct &amp; Behaviour</h3>
      <p class="text-xs text-gray-400 mb-4">Rate the student's conduct for this term. These ratings will appear on the printed report card.</p>

      <div class="space-y-3">
        @foreach(\App\Models\ReportCard::CONDUCT_TRAITS as $key => $label)
          @php $current = old("conduct.$key", $reportCard->conduct_ratings[$key] ?? ''); @endphp
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-700 w-40 shrink-0">{{ $label }}</span>
            <div class="flex gap-2 flex-wrap">
              @foreach(\App\Models\ReportCard::CONDUCT_RATINGS as $rating)
                @php
                  $selected = $current === $rating;
                  $color = match($rating) {
                    'Excellent' => $selected ? 'bg-emerald-600 text-white border-emerald-600' : 'border-gray-200 text-gray-600 hover:border-emerald-400 hover:text-emerald-600',
                    'Very Good' => $selected ? 'bg-blue-600 text-white border-blue-600'       : 'border-gray-200 text-gray-600 hover:border-blue-400 hover:text-blue-600',
                    'Good'      => $selected ? 'bg-amber-500 text-white border-amber-500'     : 'border-gray-200 text-gray-600 hover:border-amber-400 hover:text-amber-600',
                    'Fair'      => $selected ? 'bg-orange-500 text-white border-orange-500'   : 'border-gray-200 text-gray-600 hover:border-orange-400 hover:text-orange-600',
                    'Poor'      => $selected ? 'bg-red-600 text-white border-red-600'         : 'border-gray-200 text-gray-600 hover:border-red-400 hover:text-red-600',
                    default     => 'border-gray-200 text-gray-600',
                  };
                @endphp
                <label class="cursor-pointer">
                  <input type="radio" name="conduct[{{ $key }}]" value="{{ $rating }}"
                         class="sr-only" {{ $selected ? 'checked' : '' }}>
                  <span class="inline-block border rounded-full px-3 py-0.5 text-xs font-medium transition-colors {{ $color }} conduct-pill"
                        data-group="{{ $key }}" data-value="{{ $rating }}">
                    {{ $rating }}
                  </span>
                </label>
              @endforeach
              {{-- Clear option --}}
              <label class="cursor-pointer">
                <input type="radio" name="conduct[{{ $key }}]" value=""
                       class="sr-only" {{ $current === '' ? 'checked' : '' }}>
                <span class="inline-block border border-dashed border-gray-300 rounded-full px-3 py-0.5 text-xs text-gray-400 hover:text-gray-600 transition-colors conduct-pill"
                      data-group="{{ $key }}" data-value="">
                  Not set
                </span>
              </label>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    {{-- ── Actions ──────────────────────────────────────────────────────── --}}
    <div class="flex gap-3 flex-wrap">
      <button type="submit"
              class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
        Save Remarks
      </button>
      <a href="{{ route('admin.report-cards.class', ['class_id' => $reportCard->school_class_id, 'term_id' => $reportCard->term_id]) }}"
         class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
        Back to Class
      </a>
      <form method="POST" action="{{ route('admin.report-cards.generate', $reportCard) }}" class="ml-auto">
        @csrf
        <button class="bg-violet-600 hover:bg-violet-700 text-white font-semibold px-4 py-2.5 rounded-lg transition-colors text-sm">
          Save &amp; Generate PDF
        </button>
      </form>
    </div>
  </form>
</div>
@endsection

@push('scripts')
<script>
// Visual toggle for conduct rating pills — highlight selected, dim others in same group
document.querySelectorAll('input[type="radio"]').forEach(radio => {
  radio.addEventListener('change', () => {
    const group = radio.name;
    // Reset all pills in this group
    document.querySelectorAll(`input[name="${group}"]`).forEach(r => {
      const pill = r.nextElementSibling;
      if (!pill) return;
      const val  = r.value;
      const base = val === '' ? 'border-dashed border-gray-300 text-gray-400 hover:text-gray-600'
                             : 'border-gray-200 text-gray-600';
      pill.className = pill.className
        .replace(/bg-\S+/g, '').replace(/text-white/g, '').replace(/border-\S+/g, '')
        .trim() + ' border ' + base;
    });
    // Highlight selected
    const selPill = radio.nextElementSibling;
    if (selPill && radio.value !== '') {
      const colorMap = {
        'Excellent': 'bg-emerald-600 text-white border-emerald-600',
        'Very Good': 'bg-blue-600 text-white border-blue-600',
        'Good':      'bg-amber-500 text-white border-amber-500',
        'Fair':      'bg-orange-500 text-white border-orange-500',
        'Poor':      'bg-red-600 text-white border-red-600',
      };
      const classes = colorMap[radio.value] || '';
      selPill.className = selPill.className
        .replace(/bg-\S+/g, '').replace(/text-white/g, '').replace(/border-\S+/g, '')
        .trim() + ' border ' + classes;
    }
  });
});
</script>
@endpush
