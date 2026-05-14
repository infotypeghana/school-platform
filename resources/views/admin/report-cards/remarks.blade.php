@extends('layouts.admin')

@section('title', 'Remarks — ' . $reportCard->student?->full_name)
@section('page-title', 'Edit Remarks')

@section('content')
<div class="max-w-xl">
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

  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <form method="POST" action="{{ route('admin.report-cards.remarks.update', $reportCard) }}" class="space-y-5">
      @csrf @method('PUT')

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

      <div class="flex gap-3">
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
            Save & Generate PDF
          </button>
        </form>
      </div>
    </form>
  </div>
</div>
@endsection
