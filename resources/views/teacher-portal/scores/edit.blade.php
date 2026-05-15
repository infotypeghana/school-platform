@extends('layouts.teacher-portal')
@section('title', 'Enter Scores — ' . $subject->name)

@push('head')
<style>
    .score-input:focus { outline: none; box-shadow: 0 0 0 2px #3b82f6; }
    .grade-pill { font-size: 0.7rem; font-weight: 700; padding: 1px 6px; border-radius: 999px; min-width: 28px; text-align: center; }
    .grade-A1 { background: #d1fae5; color: #065f46; }
    .grade-B2,.grade-B3 { background: #dbeafe; color: #1e40af; }
    .grade-C4,.grade-C5,.grade-C6 { background: #fef9c3; color: #854d0e; }
    .grade-D7,.grade-E8 { background: #ffedd5; color: #9a3412; }
    .grade-F9 { background: #fee2e2; color: #991b1b; }
    .grade-NA { background: #f3f4f6; color: #6b7280; }
</style>
@endpush

@section('content')

{{-- Breadcrumb --}}
<div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
    <a href="{{ route('teacher.portal.scores') }}" class="hover:text-blue-600">Score Entry</a>
    <span>›</span>
    <span class="text-gray-900 font-medium">{{ $subject->name }}</span>
    <span>›</span>
    <span class="text-gray-600">{{ $term->term_name }} · {{ $term->academicYear?->year_label }}</span>
</div>

<form method="POST" action="{{ route('teacher.portal.scores.update') }}">
    @csrf @method('PUT')
    <input type="hidden" name="subject_id" value="{{ $subject->id }}">
    <input type="hidden" name="term_id"    value="{{ $term->id }}">

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Header --}}
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h1 class="text-base font-bold text-gray-900">{{ $subject->name }}</h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $class?->name }} · {{ $term->term_name }} — {{ $term->academicYear?->year_label }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400">CA max: {{ $caMax }} · Exam max: {{ $examMax }} · Total: 100</span>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                    Save Scores
                </button>
            </div>
        </div>

        @if($students->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-400 text-center">No active students in this class.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 w-10">#</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Student</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 w-24">CA <span class="font-normal">/{{ $caMax }}</span></th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 w-24">Exam <span class="font-normal">/{{ $examMax }}</span></th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 w-20">Total</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 w-16">Grade</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50" id="score-body">
                        @foreach($students as $i => $student)
                            @php
                                $a    = $existing->get($student->id);
                                $ca   = $a?->ca_score;
                                $exam = $a?->exam_score;
                            @endphp
                            <tr class="hover:bg-gray-50 score-row" data-row="{{ $student->id }}">
                                <td class="px-4 py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800">{{ $student->full_name }}</p>
                                    <p class="text-xs text-gray-400">{{ $student->admission_number }}</p>
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="number" name="scores[{{ $student->id }}][ca]"
                                           value="{{ $ca !== null ? number_format($ca, 1, '.', '') : '' }}"
                                           min="0" max="{{ $caMax }}" step="0.5"
                                           class="score-input ca-input w-20 text-center border border-gray-200 rounded-lg py-1.5 text-sm
                                                  focus:border-blue-400 hover:border-gray-300"
                                           data-row="{{ $student->id }}" placeholder="—">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="number" name="scores[{{ $student->id }}][exam]"
                                           value="{{ $exam !== null ? number_format($exam, 1, '.', '') : '' }}"
                                           min="0" max="{{ $examMax }}" step="0.5"
                                           class="score-input exam-input w-20 text-center border border-gray-200 rounded-lg py-1.5 text-sm
                                                  focus:border-blue-400 hover:border-gray-300"
                                           data-row="{{ $student->id }}" placeholder="—">
                                </td>
                                <td class="px-4 py-2 text-center font-semibold text-gray-900 total-cell" id="total-{{ $student->id }}">
                                    @if($ca !== null && $exam !== null)
                                        {{ number_format($ca + $exam, 1) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center" id="grade-{{ $student->id }}">
                                    @if($a)
                                        <span class="grade-pill grade-{{ $a->grade }}">{{ $a->grade }}</span>
                                    @else
                                        <span class="grade-pill grade-NA">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                <p class="text-xs text-gray-400">{{ $students->count() }} student{{ $students->count() === 1 ? '' : 's' }}</p>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
                    Save Scores
                </button>
            </div>
        @endif
    </div>
</form>

@push('scripts')
<script>
// Tenant grading scale — injected from server so live preview matches saved grade
const GRADE_SCALE = @json(array_values(\App\Services\GradeCalculator::tenantScale(app()->bound('currentTenant') ? app('currentTenant') : null)));

function calcGrade(total) {
    const t = Math.floor(Math.max(0, Math.min(100, total)));
    for (const band of GRADE_SCALE) {
        if (t >= band.min && t <= band.max) return band.grade;
    }
    return 'F9';
}

function updateRow(rowId) {
    const row      = document.querySelector(`tr[data-row="${rowId}"]`);
    const caInput  = row.querySelector('.ca-input');
    const examInput= row.querySelector('.exam-input');
    const totalCell= document.getElementById(`total-${rowId}`);
    const gradeCell= document.getElementById(`grade-${rowId}`);

    const ca   = caInput.value   !== '' ? parseFloat(caInput.value)   : null;
    const exam = examInput.value !== '' ? parseFloat(examInput.value) : null;

    if (ca !== null && exam !== null) {
        const total = ca + exam;
        totalCell.textContent = total.toFixed(1);
        const grade = calcGrade(total);
        gradeCell.innerHTML = `<span class="grade-pill grade-${grade}">${grade}</span>`;
    } else {
        totalCell.textContent = '—';
        gradeCell.innerHTML   = '<span class="grade-pill grade-NA">—</span>';
    }
}

document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('input', () => updateRow(input.dataset.row));
});
</script>
@endpush

@endsection
