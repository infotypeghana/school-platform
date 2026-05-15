@extends('layouts.admin')

@section('title', 'Grading Settings')
@section('page-title', 'Settings')

@section('content')
{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2">
  <a href="{{ route('admin.settings.school') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    School Profile
  </a>
  <a href="{{ route('admin.settings.grading') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
    Grading
  </a>
  <a href="{{ route('admin.settings.account') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    Account
  </a>
</div>

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

@if($errors->any())
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

{{-- Current mode banner --}}
@if($tenant->grading_settings)
  <div class="mb-5 flex items-center justify-between gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
    <div class="flex items-center gap-2.5">
      <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="text-sm text-amber-800 font-medium">Custom grading scale is active for this school.</span>
    </div>
    <form method="POST" action="{{ route('admin.settings.grading.reset') }}">
      @csrf @method('DELETE')
      <button type="submit"
              onclick="return confirm('Reset to GES standard scale? This cannot be undone.')"
              class="text-xs text-amber-700 hover:text-red-700 font-semibold underline whitespace-nowrap transition-colors">
        Reset to GES Default
      </button>
    </form>
  </div>
@else
  <div class="mb-5 flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3">
    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span class="text-sm text-emerald-800">Using <strong>GES standard scale</strong> (A1–F9, CA 30 / Exam 70). Customise below to override.</span>
  </div>
@endif

<form method="POST" action="{{ route('admin.settings.grading.update') }}" id="gradingForm">
  @csrf @method('PUT')

  <div class="max-w-3xl space-y-6">

    {{-- ── Score Weights ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
      <h2 class="text-sm font-semibold text-gray-800 mb-1">Score Weights</h2>
      <p class="text-xs text-gray-500 mb-4">CA + Exam must add up to exactly 100.</p>

      <div class="grid grid-cols-2 gap-4 max-w-xs">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">CA Max</label>
          <input type="number" name="ca_max" id="ca_max"
                 value="{{ old('ca_max', $settings['ca_max']) }}"
                 min="1" max="99" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 oninput="syncExam(this)">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Exam Max</label>
          <input type="number" name="exam_max" id="exam_max"
                 value="{{ old('exam_max', $settings['exam_max']) }}"
                 min="1" max="99" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 oninput="syncCA(this)">
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-2" id="weightHint">
        Total: <span id="weightTotal" class="font-semibold text-gray-700">{{ $settings['ca_max'] + $settings['exam_max'] }}</span>
        <span id="weightOk" class="{{ ($settings['ca_max'] + $settings['exam_max']) === 100 ? 'text-emerald-600' : 'text-red-500' }}">
          {{ ($settings['ca_max'] + $settings['exam_max']) === 100 ? '✓ Good' : '✗ Must be 100' }}
        </span>
      </p>
    </div>

    {{-- ── Grading Scale ─────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-sm font-semibold text-gray-800">Grading Scale</h2>
          <p class="text-xs text-gray-500 mt-0.5">Define the grade bands (score ranges). Rows are sorted automatically.</p>
        </div>
        <button type="button" onclick="addRow()"
                class="inline-flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 font-semibold border border-blue-300 hover:border-blue-500 px-3 py-1.5 rounded-lg transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Add Band
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 font-semibold">
              <th class="px-3 py-2 text-left rounded-tl-lg">Min Score</th>
              <th class="px-3 py-2 text-left">Max Score</th>
              <th class="px-3 py-2 text-left">Grade</th>
              <th class="px-3 py-2 text-left">Remark</th>
              <th class="px-3 py-2 text-left">Points</th>
              <th class="px-3 py-2 rounded-tr-lg"></th>
            </tr>
          </thead>
          <tbody id="scaleBody" class="divide-y divide-gray-100">
            @foreach($settings['scale'] as $i => $band)
            <tr class="scale-row">
              <td class="px-2 py-1.5">
                <input type="number" name="scale[{{ $i }}][min]"
                       value="{{ old("scale.$i.min", $band['min']) }}"
                       min="0" max="100" required
                       class="w-20 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
              </td>
              <td class="px-2 py-1.5">
                <input type="number" name="scale[{{ $i }}][max]"
                       value="{{ old("scale.$i.max", $band['max']) }}"
                       min="0" max="100" required
                       class="w-20 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
              </td>
              <td class="px-2 py-1.5">
                <input type="text" name="scale[{{ $i }}][grade]"
                       value="{{ old("scale.$i.grade", $band['grade']) }}"
                       maxlength="10" required
                       class="w-16 border border-gray-200 rounded px-2 py-1 text-xs text-center font-mono font-bold focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
              </td>
              <td class="px-2 py-1.5">
                <input type="text" name="scale[{{ $i }}][remark]"
                       value="{{ old("scale.$i.remark", $band['remark']) }}"
                       maxlength="50" required
                       class="w-28 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
              </td>
              <td class="px-2 py-1.5">
                <input type="number" name="scale[{{ $i }}][points]"
                       value="{{ old("scale.$i.points", $band['points']) }}"
                       min="1" max="20" required
                       class="w-16 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
              </td>
              <td class="px-2 py-1.5 text-center">
                <button type="button" onclick="removeRow(this)"
                        class="text-gray-300 hover:text-red-500 transition-colors"
                        title="Remove band">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <p class="text-xs text-gray-400 mt-3">
        <strong>Points</strong> are used for BECE-style aggregate (lower = better). Typically 1 for the top grade, increasing downward.
      </p>
    </div>

    {{-- ── GES Default Reference ─────────────────────────────────────────── --}}
    <details class="bg-gray-50 border border-gray-200 rounded-xl">
      <summary class="px-5 py-3 text-sm font-medium text-gray-700 cursor-pointer select-none">
        GES Standard Scale Reference
      </summary>
      <div class="px-5 pb-4 pt-2 overflow-x-auto">
        <table class="text-xs text-gray-600 border-collapse">
          <thead>
            <tr class="text-gray-400 font-semibold">
              <th class="pr-6 py-1 text-left">Grade</th>
              <th class="pr-6 py-1 text-left">Range</th>
              <th class="pr-6 py-1 text-left">Remark</th>
              <th class="py-1 text-left">Points</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach($defaultScale as $b)
            <tr>
              <td class="pr-6 py-1 font-bold font-mono">{{ $b['grade'] }}</td>
              <td class="pr-6 py-1">{{ $b['min'] }}–{{ $b['max'] }}</td>
              <td class="pr-6 py-1">{{ $b['remark'] }}</td>
              <td class="py-1">{{ $b['points'] }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </details>

    {{-- ── Actions ───────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-3">
      <button type="submit"
              class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
        Save Grading Settings
      </button>
      @if($tenant->grading_settings)
        <form method="POST" action="{{ route('admin.settings.grading.reset') }}">
          @csrf @method('DELETE')
          <button type="submit"
                  onclick="return confirm('Reset to GES standard scale? This cannot be undone.')"
                  class="text-sm text-gray-500 hover:text-red-600 font-medium transition-colors">
            Reset to GES Default
          </button>
        </form>
      @endif
    </div>

  </div>
</form>
@endsection

@push('scripts')
<script>
let rowIndex = {{ count($settings['scale']) }};

// Keep rows re-indexed when the DOM changes so name attributes stay sequential
function reindex() {
  document.querySelectorAll('#scaleBody .scale-row').forEach((row, i) => {
    row.querySelectorAll('input').forEach(input => {
      // name="scale[OLD][field]" → "scale[i][field]"
      input.name = input.name.replace(/scale\[\d+\]/, `scale[${i}]`);
    });
  });
  rowIndex = document.querySelectorAll('#scaleBody .scale-row').length;
}

function addRow() {
  const tbody = document.getElementById('scaleBody');
  const idx   = rowIndex;
  const tr    = document.createElement('tr');
  tr.className = 'scale-row';
  tr.innerHTML = `
    <td class="px-2 py-1.5">
      <input type="number" name="scale[${idx}][min]" value="0" min="0" max="100" required
             class="w-20 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
    </td>
    <td class="px-2 py-1.5">
      <input type="number" name="scale[${idx}][max]" value="0" min="0" max="100" required
             class="w-20 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
    </td>
    <td class="px-2 py-1.5">
      <input type="text" name="scale[${idx}][grade]" value="" maxlength="10" required
             class="w-16 border border-gray-200 rounded px-2 py-1 text-xs text-center font-mono font-bold focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
             placeholder="e.g. A1">
    </td>
    <td class="px-2 py-1.5">
      <input type="text" name="scale[${idx}][remark]" value="" maxlength="50" required
             class="w-28 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400"
             placeholder="e.g. Excellent">
    </td>
    <td class="px-2 py-1.5">
      <input type="number" name="scale[${idx}][points]" value="1" min="1" max="20" required
             class="w-16 border border-gray-200 rounded px-2 py-1 text-xs text-center focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
    </td>
    <td class="px-2 py-1.5 text-center">
      <button type="button" onclick="removeRow(this)"
              class="text-gray-300 hover:text-red-500 transition-colors" title="Remove band">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
      </button>
    </td>`;
  tbody.appendChild(tr);
  rowIndex++;
}

function removeRow(btn) {
  const rows = document.querySelectorAll('#scaleBody .scale-row');
  if (rows.length <= 1) {
    alert('You must have at least one grade band.');
    return;
  }
  btn.closest('tr').remove();
  reindex();
}

// Weight sync helpers
function syncExam(caInput) {
  const ca   = parseInt(caInput.value) || 0;
  const exam = Math.max(1, Math.min(99, 100 - ca));
  document.getElementById('exam_max').value = exam;
  updateWeightHint(ca, exam);
}
function syncCA(examInput) {
  const exam = parseInt(examInput.value) || 0;
  const ca   = Math.max(1, Math.min(99, 100 - exam));
  document.getElementById('ca_max').value = ca;
  updateWeightHint(ca, exam);
}
function updateWeightHint(ca, exam) {
  const total = ca + exam;
  document.getElementById('weightTotal').textContent = total;
  const ok = document.getElementById('weightOk');
  if (total === 100) {
    ok.textContent = '✓ Good';
    ok.className = 'font-semibold text-emerald-600';
  } else {
    ok.textContent = '✗ Must be 100';
    ok.className = 'font-semibold text-red-500';
  }
}
</script>
@endpush
