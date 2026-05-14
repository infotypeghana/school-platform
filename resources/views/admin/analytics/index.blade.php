@extends('layouts.admin')

@section('title', 'Performance Analytics')
@section('page-title', 'Performance Analytics')

@section('content')

{{-- Filters + export --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
<form method="GET" class="flex flex-wrap gap-2">
  <select name="term_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All terms</option>
    @foreach($terms as $term)
      <option value="{{ $term->id }}" {{ $termId == $term->id ? 'selected' : '' }}>
        {{ $term->term_name }} · {{ $term->academicYear?->year_label }}
      </option>
    @endforeach
  </select>
  <select name="class_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All classes</option>
    @foreach($classes as $class)
      <option value="{{ $class->id }}" {{ $classId == $class->id ? 'selected' : '' }}>{{ $class->full_name }}</option>
    @endforeach
  </select>
  <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">Apply</button>
  @if($termId || $classId)
    <a href="{{ route('admin.analytics.index') }}" class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-500 hover:bg-gray-50 transition-colors">Clear</a>
  @endif
</form>

{{-- Export buttons --}}
<div class="flex gap-2">
  @php $qs = http_build_query(array_filter(['term_id' => $termId, 'class_id' => $classId])); @endphp
  <a href="{{ route('admin.analytics.export.csv') . ($qs ? '?' . $qs : '') }}"
     class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
    ⬇ CSV
  </a>
  <a href="{{ route('admin.analytics.export.pdf') . ($qs ? '?' . $qs : '') }}"
     class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors">
    ⬇ PDF
  </a>
</div>
</div>

{{-- KPI row --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
  @foreach([
    ['Total Students', $studentCounts['total'],  'text-gray-900',   'bg-gray-50'],
    ['Active',         $studentCounts['active'],  'text-emerald-700','bg-emerald-50'],
    ['Male',           $studentCounts['male'],    'text-blue-700',   'bg-blue-50'],
    ['Female',         $studentCounts['female'],  'text-pink-700',   'bg-pink-50'],
  ] as [$label, $val, $color, $bg])
    <div class="rounded-xl border border-gray-100 shadow-sm p-4 {{ $bg }}">
      <p class="text-xs font-medium text-gray-500 mb-1">{{ $label }}</p>
      <p class="text-2xl font-bold {{ $color }}">{{ number_format($val) }}</p>
    </div>
  @endforeach
</div>

{{-- Pass rate banner --}}
@if($passRate !== null)
<div class="mb-6 rounded-xl border {{ $passRate >= 70 ? 'border-emerald-200 bg-emerald-50' : ($passRate >= 50 ? 'border-amber-200 bg-amber-50' : 'border-red-200 bg-red-50') }} px-6 py-4 flex items-center justify-between">
  <div>
    <p class="text-sm font-semibold text-gray-700">Overall Pass Rate (C6 or better)</p>
    <p class="text-xs text-gray-500 mt-0.5">{{ number_format($passCount) }} of {{ number_format($totalCount) }} assessments</p>
  </div>
  <p class="text-4xl font-bold {{ $passRate >= 70 ? 'text-emerald-700' : ($passRate >= 50 ? 'text-amber-700' : 'text-red-600') }}">
    {{ $passRate }}%
  </p>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

  {{-- Grade distribution --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Grade Distribution</h3>
    <canvas id="gradeChart" height="220"></canvas>
  </div>

  {{-- Gender performance --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Average Score by Gender</h3>
    @if($genderPerf->isEmpty())
      <p class="text-sm text-gray-400 py-8 text-center">No gender data available.</p>
    @else
      <canvas id="genderChart" height="220"></canvas>
    @endif
  </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

  {{-- Subject performance --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="text-sm font-semibold text-gray-700">Subject Performance</h3>
    </div>
    @if($subjectPerformance->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">No assessment data.</p>
    @else
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Subject</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Avg Score</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Count</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-32">Bar</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($subjectPerformance as $row)
            @php $pct = min(100, $row->avg_score); $color = $pct >= 70 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-400' : 'bg-red-400'); @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-700 font-medium">{{ $row->subject?->name ?? '—' }}</td>
              <td class="px-4 py-2 text-right font-semibold {{ $pct >= 70 ? 'text-emerald-700' : ($pct >= 50 ? 'text-amber-600' : 'text-red-500') }}">
                {{ number_format($row->avg_score, 1) }}
              </td>
              <td class="px-4 py-2 text-right text-gray-400 text-xs">{{ $row->count }}</td>
              <td class="px-4 py-2">
                <div class="h-2 rounded-full bg-gray-100">
                  <div class="h-2 rounded-full {{ $color }}" style="width: {{ $pct }}%"></div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- Class performance --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="text-sm font-semibold text-gray-700">Class Performance</h3>
    </div>
    @if($classPerf->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">No assessment data.</p>
    @else
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Class</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Avg Score</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Assessments</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-32">Bar</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($classPerf as $row)
            @php $pct = min(100, $row->avg_score); $color = $pct >= 70 ? 'bg-emerald-500' : ($pct >= 50 ? 'bg-amber-400' : 'bg-red-400'); @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-700 font-medium">{{ $row->schoolClass?->full_name ?? '—' }}</td>
              <td class="px-4 py-2 text-right font-semibold {{ $pct >= 70 ? 'text-emerald-700' : ($pct >= 50 ? 'text-amber-600' : 'text-red-500') }}">
                {{ number_format($row->avg_score, 1) }}
              </td>
              <td class="px-4 py-2 text-right text-gray-400 text-xs">{{ $row->count }}</td>
              <td class="px-4 py-2">
                <div class="h-2 rounded-full bg-gray-100">
                  <div class="h-2 rounded-full {{ $color }}" style="width: {{ $pct }}%"></div>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>

{{-- Term trend --}}
@if($termTrend->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
  <h3 class="text-sm font-semibold text-gray-700 mb-4">Score Trend by Term</h3>
  <canvas id="trendChart" height="100"></canvas>
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  // ── Grade distribution ──────────────────────────────────────────────────────
  const gradeCtx = document.getElementById('gradeChart');
  if (gradeCtx) {
    const gradeColors = {
      A1: '#10b981', B2: '#34d399', B3: '#6ee7b7',
      C4: '#fbbf24', C5: '#fcd34d', C6: '#fde68a',
      D7: '#f97316', E8: '#fb923c', F9: '#ef4444',
    };
    const gradeLabels = @json($gradeLabels);
    const gradeCounts = @json($gradeCounts);
    new Chart(gradeCtx, {
      type: 'bar',
      data: {
        labels: gradeLabels,
        datasets: [{
          label: 'Students',
          data: gradeCounts,
          backgroundColor: gradeLabels.map(g => gradeColors[g] ?? '#9ca3af'),
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      }
    });
  }

  // ── Gender performance ──────────────────────────────────────────────────────
  const genderCtx = document.getElementById('genderChart');
  if (genderCtx) {
    const genderData = @json($genderPerf->values());
    new Chart(genderCtx, {
      type: 'bar',
      data: {
        labels: genderData.map(g => g.gender ? (g.gender.charAt(0).toUpperCase() + g.gender.slice(1)) : 'Unknown'),
        datasets: [{
          label: 'Average Score',
          data: genderData.map(g => parseFloat(g.avg_score)),
          backgroundColor: genderData.map(g =>
            g.gender === 'male' ? '#3b82f6' : g.gender === 'female' ? '#ec4899' : '#9ca3af'
          ),
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } },
      }
    });
  }

  // ── Term trend ─────────────────────────────────────────────────────────────
  const trendCtx = document.getElementById('trendChart');
  if (trendCtx) {
    const trendData = @json($termTrend->values());
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: trendData.map(t => (t.term?.term_name ?? 'Term') + ' · ' + (t.term?.academic_year?.year_label ?? '')),
        datasets: [{
          label: 'Class Average',
          data: trendData.map(t => parseFloat(t.avg_score)),
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.1)',
          tension: 0.3,
          fill: true,
          pointRadius: 5,
          pointBackgroundColor: '#3b82f6',
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: false, min: 0, max: 100, ticks: { stepSize: 20 } }
        }
      }
    });
  }

});
</script>
@endpush
