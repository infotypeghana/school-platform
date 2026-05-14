<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: sans-serif; font-size: 11px; color: #1f2937; padding: 30px; }
    h1 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
    h2 { font-size: 13px; font-weight: 700; margin: 18px 0 8px; color: #1e40af; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
    .meta { font-size: 10px; color: #6b7280; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    th { background: #f3f4f6; text-align: left; padding: 5px 8px; font-size: 10px; font-weight: 600; color: #374151; border: 1px solid #e5e7eb; }
    td { padding: 4px 8px; border: 1px solid #e5e7eb; color: #374151; }
    tr:nth-child(even) td { background: #f9fafb; }
    .badge-pass { color: #065f46; font-weight: 600; }
    .badge-fail { color: #991b1b; font-weight: 600; }
    .summary-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; }
    .summary-box p { margin-bottom: 3px; }
    .footer { margin-top: 30px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
  </style>
</head>
<body>

<h1>{{ $tenant->name }} — Performance Analytics</h1>
<p class="meta">
  Generated: {{ now()->format('d M Y, H:i') }}
  @if($term) · {{ $term->term_name }} {{ $term->academicYear?->year_label }} @endif
  @if($class) · {{ $class->full_name }} @endif
</p>

{{-- Summary --}}
<div class="summary-box">
  <p><strong>Total Students:</strong> {{ $studentCounts['total'] }} ({{ $studentCounts['active'] }} active)</p>
  <p><strong>Male:</strong> {{ $studentCounts['male'] }} &nbsp;|&nbsp; <strong>Female:</strong> {{ $studentCounts['female'] }}</p>
  <p>
    <strong>Pass Rate (C6 or better):</strong>
    @if($passRate !== null)
      <span class="{{ $passRate >= 50 ? 'badge-pass' : 'badge-fail' }}">{{ $passRate }}%</span>
      ({{ $passCount }} / {{ $totalCount }} assessments)
    @else
      N/A
    @endif
  </p>
</div>

{{-- Subject Performance --}}
<h2>Subject Performance (Average Score)</h2>
<table>
  <thead>
    <tr><th>Subject</th><th>Avg Score</th><th>Assessments</th></tr>
  </thead>
  <tbody>
    @forelse($subjectPerformance as $row)
      <tr>
        <td>{{ $row->subject?->name ?? '—' }}</td>
        <td>{{ $row->avg_score }}/100</td>
        <td>{{ $row->count }}</td>
      </tr>
    @empty
      <tr><td colspan="3" style="text-align:center;color:#9ca3af;">No data</td></tr>
    @endforelse
  </tbody>
</table>

{{-- Grade Distribution --}}
<h2>Grade Distribution</h2>
<table>
  <thead>
    <tr>
      @foreach($gradeLabels as $g)<th>{{ $g }}</th>@endforeach
    </tr>
  </thead>
  <tbody>
    <tr>
      @foreach($gradeCounts as $c)<td>{{ $c }}</td>@endforeach
    </tr>
  </tbody>
</table>

{{-- Gender Performance --}}
<h2>Gender Performance</h2>
<table>
  <thead><tr><th>Gender</th><th>Avg Score</th><th>Count</th></tr></thead>
  <tbody>
    @foreach(['male','female'] as $gender)
      @php $g = $genderPerf[$gender] ?? null; @endphp
      <tr>
        <td>{{ ucfirst($gender) }}</td>
        <td>{{ $g?->avg_score ?? 'N/A' }}</td>
        <td>{{ $g?->count ?? 0 }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

{{-- Class Performance --}}
<h2>Class Performance</h2>
<table>
  <thead><tr><th>Class</th><th>Avg Score</th><th>Count</th></tr></thead>
  <tbody>
    @forelse($classPerf as $row)
      <tr>
        <td>{{ $row->schoolClass?->full_name ?? '—' }}</td>
        <td>{{ $row->avg_score }}/100</td>
        <td>{{ $row->count }}</td>
      </tr>
    @empty
      <tr><td colspan="3" style="text-align:center;color:#9ca3af;">No data</td></tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  {{ $tenant->name }} · Printed {{ now()->format('d M Y H:i') }} · SchoolMS
</div>

</body>
</html>
