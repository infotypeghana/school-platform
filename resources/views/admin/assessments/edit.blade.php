@extends('layouts.admin')

@section('title', 'Score Entry — ' . $class->full_name)
@section('page-title', 'Score Entry')

@section('content')

{{-- Breadcrumb / context bar --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $class->full_name }}</h2>
    <p class="text-sm text-gray-500">{{ $term->term_name }} · {{ $term->academicYear?->year_label }} · {{ $students->count() }} student(s) · {{ $subjects->count() }} subject(s)</p>
  </div>
  <a href="{{ route('admin.assessments.index') }}"
     class="text-sm text-blue-600 hover:underline">← Change class / term</a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

@if($students->isEmpty())
  <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm">
    No active students found in this class.
  </div>
@elseif($subjects->isEmpty())
  <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm">
    No subjects have been assigned to this class yet.
  </div>
@else

<form method="POST" action="{{ route('admin.assessments.update') }}" id="scoreForm">
  @csrf
  @method('PUT')
  <input type="hidden" name="class_id" value="{{ $class->id }}">
  <input type="hidden" name="term_id"  value="{{ $term->id }}">

  {{-- Legend --}}
  <div class="flex items-center gap-6 mb-3 text-xs text-gray-500">
    <span class="font-medium text-gray-700">CA max: <span class="text-blue-600 font-bold">30</span></span>
    <span class="font-medium text-gray-700">Exam max: <span class="text-blue-600 font-bold">70</span></span>
    <span class="font-medium text-gray-700">Total: <span class="text-blue-600 font-bold">100</span></span>
    <span class="text-gray-400">Leave blank to skip</span>
  </div>

  <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="bg-gray-900 text-white">
          <th class="sticky left-0 bg-gray-900 px-4 py-3 text-left font-semibold whitespace-nowrap z-10">Student</th>
          @foreach($subjects as $subject)
            <th class="px-2 py-3 text-center font-semibold min-w-[120px]" colspan="2">
              <div class="truncate max-w-[110px] mx-auto text-xs leading-tight">{{ $subject->name }}</div>
              <div class="flex gap-1 mt-1 justify-center text-xs font-normal text-gray-300">
                <span class="w-14 text-center">CA/30</span>
                <span class="w-14 text-center">Exam/70</span>
              </div>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @foreach($students as $student)
          <tr class="hover:bg-blue-50/30 transition-colors {{ $loop->even ? 'bg-gray-50/50' : 'bg-white' }}">
            {{-- Student name --}}
            <td class="sticky left-0 {{ $loop->even ? 'bg-gray-50' : 'bg-white' }} px-4 py-2 font-medium text-gray-900 whitespace-nowrap z-10 border-r border-gray-100">
              {{ $student->full_name }}
              <div class="text-xs text-gray-400">{{ $student->admission_number }}</div>
            </td>

            @foreach($subjects as $subject)
              @php
                $assessment = $existing[$student->id][$subject->id] ?? null;
              @endphp
              <td class="px-1 py-1.5" colspan="2">
                <div class="flex gap-1">
                  {{-- CA score --}}
                  <input
                    type="number"
                    name="scores[{{ $student->id }}][{{ $subject->id }}][ca]"
                    value="{{ old("scores.{$student->id}.{$subject->id}.ca", $assessment?->ca_score) }}"
                    min="0" max="30" step="0.5"
                    placeholder="CA"
                    class="w-14 border border-gray-200 rounded px-1.5 py-1 text-center text-xs focus:ring-2 focus:ring-blue-400 focus:border-blue-400 @error("scores.{$student->id}.{$subject->id}.ca") border-red-400 @enderror"
                    oninput="calcTotal(this)"
                  >
                  {{-- Exam score --}}
                  <input
                    type="number"
                    name="scores[{{ $student->id }}][{{ $subject->id }}][exam]"
                    value="{{ old("scores.{$student->id}.{$subject->id}.exam", $assessment?->exam_score) }}"
                    min="0" max="70" step="0.5"
                    placeholder="Exam"
                    class="w-14 border border-gray-200 rounded px-1.5 py-1 text-center text-xs focus:ring-2 focus:ring-blue-400 focus:border-blue-400 @error("scores.{$student->id}.{$subject->id}.exam") border-red-400 @enderror"
                    oninput="calcTotal(this)"
                  >
                </div>
                {{-- Live total preview --}}
                @php $total = $assessment ? number_format($assessment->total_score, 1) : null; @endphp
                <div class="text-center mt-0.5 text-xs font-semibold total-preview
                  {{ $assessment ? ($assessment->grade === 'F9' ? 'text-red-500' : ($assessment->total_score >= 60 ? 'text-emerald-600' : 'text-amber-600')) : 'text-gray-300' }}"
                  id="total_{{ $student->id }}_{{ $subject->id }}">
                  @if($assessment)
                    {{ $total }} <span class="font-normal text-gray-400">({{ $assessment->grade }})</span>
                  @else
                    —
                  @endif
                </div>
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Submit --}}
  <div class="mt-5 flex items-center gap-3">
    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
      Save All Scores
    </button>
    <span class="text-xs text-gray-400">Scores are auto-graded on save (CA + Exam = Total → Grade)</span>
  </div>
</form>

@endif

<script>
function calcTotal(input) {
  const cell = input.closest('td');
  const inputs = cell.querySelectorAll('input[type="number"]');
  const ca   = parseFloat(inputs[0].value) || 0;
  const exam = parseFloat(inputs[1].value) || 0;
  const total = ca + exam;

  // Extract student_id and subject_id from name attribute
  const match = inputs[0].name.match(/scores\[(\d+)\]\[(\d+)\]/);
  if (!match) return;
  const div = document.getElementById(`total_${match[1]}_${match[2]}`);
  if (!div) return;

  if (inputs[0].value === '' && inputs[1].value === '') {
    div.textContent = '—';
    div.className = 'text-center mt-0.5 text-xs font-semibold total-preview text-gray-300';
    return;
  }

  const grade = getGrade(total);
  div.textContent = total.toFixed(1) + ' (' + grade + ')';
  div.className = 'text-center mt-0.5 text-xs font-semibold total-preview ' +
    (grade === 'F9' ? 'text-red-500' : total >= 60 ? 'text-emerald-600' : 'text-amber-600');
}

function getGrade(s) {
  if (s >= 80) return 'A1';
  if (s >= 70) return 'B2';
  if (s >= 60) return 'B3';
  if (s >= 55) return 'C4';
  if (s >= 50) return 'C5';
  if (s >= 45) return 'C6';
  if (s >= 40) return 'D7';
  if (s >= 35) return 'E8';
  return 'F9';
}
</script>
@endsection
