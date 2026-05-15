<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
@php
  // Sanitise primary colour — must be a valid 3 or 6-digit hex code.
  // Falls back to a professional navy if the stored value is absent or malformed.
  $pc = preg_match('/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $primaryColor ?? '')
      ? $primaryColor
      : '#1a3a6e';
@endphp
<style>
  :root { --pc: {{ $pc }}; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    background: #fff;
  }

  /* ── Page layout ── */
  .page { width: 100%; padding: 14mm 14mm 10mm 14mm; }

  /* ── Header ── */
  .header { display: table; width: 100%; border-bottom: 3px solid {{ $pc }}; padding-bottom: 8px; margin-bottom: 10px; }
  .header-logo { display: table-cell; width: 70px; vertical-align: middle; }
  .header-logo img { width: 60px; height: 60px; border-radius: 4px; object-fit: contain; }
  .header-logo .logo-placeholder {
    width: 60px; height: 60px; border-radius: 4px;
    background: {{ $pc }}; color: #fff;
    font-size: 22pt; font-weight: bold;
    text-align: center; line-height: 60px;
  }
  .header-info { display: table-cell; text-align: center; vertical-align: middle; }
  .header-info h1 { font-size: 16pt; font-weight: bold; color: {{ $pc }}; text-transform: uppercase; letter-spacing: 1px; }
  .header-info p  { font-size: 8.5pt; color: #555; margin-top: 2px; }
  .header-right { display: table-cell; width: 80px; text-align: right; vertical-align: middle; }

  /* ── Report title banner ── */
  .report-title {
    background: {{ $pc }}; color: #fff;
    text-align: center; font-size: 11pt;
    font-weight: bold; padding: 5px;
    text-transform: uppercase; letter-spacing: 2px;
    margin-bottom: 10px;
  }

  /* ── Student info table ── */
  .student-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .student-info td { padding: 4px 6px; font-size: 9.5pt; }
  .student-info .label { font-weight: bold; color: #555; width: 110px; }
  .student-info .value { border-bottom: 1px solid #ccc; min-width: 120px; }
  .info-row { display: table; width: 100%; }
  .info-col { display: table-cell; width: 50%; }

  /* ── Scores table ── */
  .scores-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .scores-table th {
    background: {{ $pc }}; color: #fff;
    padding: 5px 6px; font-size: 9pt;
    border: 1px solid {{ $pc }}; text-align: center;
  }
  .scores-table td { padding: 4px 6px; border: 1px solid #ddd; font-size: 9pt; }
  .scores-table tbody tr:nth-child(even) { background: #f7f9fc; }
  .scores-table .subject-col { font-weight: 600; }
  .scores-table .grade-col { font-weight: bold; text-align: center; }
  .scores-table .pos-col { text-align: center; color: #555; }
  .scores-table tfoot td { background: #eef1f7; font-weight: bold; border-top: 2px solid {{ $pc }}; }

  /* Grade colour coding */
  .grade-A1 { color: #15803d; }
  .grade-B2, .grade-B3 { color: #1d4ed8; }
  .grade-C4, .grade-C5, .grade-C6 { color: #d97706; }
  .grade-D7, .grade-E8 { color: #9a3412; }
  .grade-F9 { color: #dc2626; font-weight: bold; }

  /* ── Summary section ── */
  .summary-grid { display: table; width: 100%; margin-bottom: 12px; }
  .summary-box {
    display: table-cell; width: 20%;
    border: 1px solid #ddd; padding: 6px 8px;
    text-align: center; vertical-align: middle;
  }
  .summary-box .s-label { font-size: 7.5pt; color: #888; text-transform: uppercase; }
  .summary-box .s-val { font-size: 14pt; font-weight: bold; color: {{ $pc }}; margin-top: 2px; }

  /* ── Attendance ── */
  .attendance-bar-bg { background: #e5e7eb; border-radius: 4px; height: 10px; width: 100%; }
  .attendance-bar    { background: #15803d; border-radius: 4px; height: 10px; }

  /* ── Remarks ── */
  .remarks-section { margin-bottom: 12px; }
  .remarks-section h3 { font-size: 9pt; font-weight: bold; color: {{ $pc }}; margin-bottom: 4px; text-transform: uppercase; }
  .remark-box {
    border: 1px solid #ddd; border-radius: 4px;
    padding: 6px 8px; font-size: 9pt; min-height: 30px;
    background: #fafafa; color: #333;
  }

  /* ── Grade scale legend ── */
  .legend { display: table; width: 100%; margin-bottom: 10px; }
  .legend-item { display: table-cell; text-align: center; border: 1px solid #ddd; padding: 3px 4px; font-size: 7.5pt; }
  .legend-item strong { display: block; font-size: 9pt; }

  /* ── Conduct & Behaviour ── */
  .conduct-section { margin-bottom: 12px; }
  .conduct-section h3 { font-size: 9pt; font-weight: bold; color: {{ $pc }}; margin-bottom: 6px; text-transform: uppercase; }
  .conduct-grid { display: table; width: 100%; border-collapse: collapse; }
  .conduct-row { display: table-row; }
  .conduct-cell { display: table-cell; width: 50%; padding: 3px 6px; border: 1px solid #eee; font-size: 8.5pt; vertical-align: middle; }
  .conduct-cell .c-label { color: #555; }
  .conduct-cell .c-rating {
    float: right; font-weight: bold; font-size: 8pt;
    padding: 1px 7px; border-radius: 10px;
  }
  .r-excellent { background: #d1fae5; color: #065f46; }
  .r-very-good { background: #dbeafe; color: #1e40af; }
  .r-good      { background: #fef9c3; color: #854d0e; }
  .r-fair      { background: #ffedd5; color: #9a3412; }
  .r-poor      { background: #fee2e2; color: #991b1b; }
  .r-none      { background: #f3f4f6; color: #9ca3af; }

  /* ── Bar chart ── */
  .barchart-section { margin-bottom: 12px; }
  .barchart-section h3 { font-size: 9pt; font-weight: bold; color: {{ $pc }}; margin-bottom: 6px; text-transform: uppercase; }
  .bar-row { display: table; width: 100%; margin-bottom: 4px; }
  .bar-subject { display: table-cell; width: 28%; font-size: 7.5pt; color: #555; vertical-align: middle; padding-right: 4px; }
  .bar-track   { display: table-cell; width: 63%; vertical-align: middle; }
  .bar-bg      { background: #e5e7eb; border-radius: 3px; height: 11px; width: 100%; }
  .bar-fill    { border-radius: 3px; height: 11px; }
  .bar-score   { display: table-cell; width: 9%; text-align: right; font-size: 7.5pt; font-weight: bold; color: #333; vertical-align: middle; padding-left: 4px; }
  .bar-grade   { display: table-cell; width: 6%; text-align: center; font-size: 7pt; font-weight: bold; vertical-align: middle; padding-left: 3px; }

  /* Bar colours by grade */
  .bar-A1 { background: #15803d; }
  .bar-B2, .bar-B3 { background: #1d4ed8; }
  .bar-C4, .bar-C5, .bar-C6 { background: #d97706; }
  .bar-D7, .bar-E8 { background: #ea580c; }
  .bar-F9 { background: #dc2626; }

  /* ── Signatures ── */
  .signatures { display: table; width: 100%; margin-top: 8px; }
  .sig-col { display: table-cell; width: 50%; text-align: center; padding: 0 12px; }
  .sig-line { border-top: 1px solid #888; margin: 22px 0 3px; }
  .sig-label { font-size: 8pt; color: #555; }

  /* ── Footer ── */
  .page-footer {
    border-top: 1px solid #ddd; margin-top: 10px;
    padding-top: 5px; text-align: center;
    font-size: 7.5pt; color: #aaa;
  }

  /* ── Watermark ── */
  .watermark {
    position: fixed; top: 50%; left: 50%;
    transform: translate(-50%, -50%) rotate(-35deg);
    font-size: 80pt; color: rgba(0,0,0,0.03);
    font-weight: bold; white-space: nowrap;
    z-index: -1; pointer-events: none;
  }
</style>
</head>
<body>
<div class="watermark">OFFICIAL</div>
<div class="page">

  {{-- ══════════════ HEADER ══════════════ --}}
  <div class="header">
    <div class="header-logo">
      @if($student->tenant?->logo)
        <img src="{{ public_path('storage/' . $student->tenant->logo) }}" alt="Logo">
      @else
        <div class="logo-placeholder">
          {{ strtoupper(substr($student->tenant?->name ?? 'S', 0, 1)) }}
        </div>
      @endif
    </div>
    <div class="header-info">
      <h1>{{ $student->tenant?->name ?? 'School Name' }}</h1>
      <p>{{ $student->tenant?->address }}</p>
      <p>Tel: {{ $student->tenant?->contact_phone }} &nbsp;|&nbsp; {{ $student->tenant?->contact_email }}</p>
    </div>
    <div class="header-right">
      {{-- Optional seal placeholder --}}
    </div>
  </div>

  {{-- ══════════════ TITLE ══════════════ --}}
  <div class="report-title">
    End of Term Report Card &nbsp;·&nbsp; {{ $term->term_name }} {{ $term->academicYear?->year_label }}
  </div>

  {{-- ══════════════ STUDENT INFO ══════════════ --}}
  <div class="info-row">
    <div class="info-col" style="padding-right:10px;">
      <table class="student-info">
        <tr>
          <td class="label">Student Name</td>
          <td class="value">{{ $student->full_name }}</td>
        </tr>
        <tr>
          <td class="label">Admission No.</td>
          <td class="value">{{ $student->admission_number ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Class</td>
          <td class="value">{{ $class->full_name }}</td>
        </tr>
      </table>
    </div>
    <div class="info-col" style="padding-left:10px;">
      <table class="student-info">
        <tr>
          <td class="label">Date of Birth</td>
          <td class="value">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</td>
        </tr>
        <tr>
          <td class="label">Gender</td>
          <td class="value">{{ ucfirst($student->gender ?? '—') }}</td>
        </tr>
        <tr>
          <td class="label">Class Teacher</td>
          <td class="value">{{ $class->classTeacher?->full_name ?? '—' }}</td>
        </tr>
      </table>
    </div>
  </div>

  {{-- ══════════════ SCORES TABLE ══════════════ --}}
  <table class="scores-table">
    <thead>
      <tr>
        <th style="width:5%; text-align:center;">#</th>
        <th style="width:32%; text-align:left;">Subject</th>
        <th style="width:10%;">CA<br><span style="font-size:7.5pt;font-weight:normal;">({{ $caMax }})</span></th>
        <th style="width:10%;">Exam<br><span style="font-size:7.5pt;font-weight:normal;">({{ $examMax }})</span></th>
        <th style="width:10%;">Total<br><span style="font-size:7.5pt;font-weight:normal;">(100)</span></th>
        <th style="width:8%;">Grade</th>
        <th style="width:10%;">Remark</th>
        <th style="width:8%;">Pos.</th>
        <th style="width:7%;">Avg</th>
      </tr>
    </thead>
    <tbody>
      @foreach($assessments as $i => $a)
      <tr>
        <td style="text-align:center; color:#888;">{{ $i + 1 }}</td>
        <td class="subject-col">{{ $a->subject?->name }}</td>
        <td style="text-align:center;">{{ number_format($a->ca_score, 1) }}</td>
        <td style="text-align:center;">{{ number_format($a->exam_score, 1) }}</td>
        <td style="text-align:center; font-weight:600;">{{ number_format($a->total_score, 1) }}</td>
        <td class="grade-col grade-{{ $a->grade }}">{{ $a->grade }}</td>
        <td style="font-size:8.5pt;">{{ $a->remark }}</td>
        <td class="pos-col">
          {{ $a->position_in_class ? $a->position_in_class . ordinal($a->position_in_class) : '—' }}
        </td>
        <td style="text-align:center; color:#555;">
          {{ $a->class_average ? number_format($a->class_average, 1) : '—' }}
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2" style="text-align:right;">Total</td>
        <td style="text-align:center;">{{ number_format($assessments->sum('ca_score'), 1) }}</td>
        <td style="text-align:center;">{{ number_format($assessments->sum('exam_score'), 1) }}</td>
        <td style="text-align:center;">{{ number_format($assessments->sum('total_score'), 1) }}</td>
        <td colspan="4"></td>
      </tr>
    </tfoot>
  </table>

  {{-- ══════════════ SUMMARY BOXES ══════════════ --}}
  <div class="summary-grid">
    <div class="summary-box">
      <div class="s-label">Overall Position</div>
      <div class="s-val">
        {{ $reportCard->overall_position
            ? $reportCard->overall_position . ordinal($reportCard->overall_position)
            : '—' }}
      </div>
      <div class="s-label">out of {{ $reportCard->out_of }}</div>
    </div>
    <div class="summary-box">
      <div class="s-label">Subjects Passed</div>
      @php
        $passed = $assessments->filter(fn($a) => \App\Services\GradeCalculator::isPassing($a->grade ?? 'F9'))->count();
      @endphp
      <div class="s-val">{{ $passed }}/{{ $assessments->count() }}</div>
    </div>
    <div class="summary-box">
      <div class="s-label">Aggregate</div>
      <div class="s-val">{{ $aggregate ?? '—' }}</div>
      <div class="s-label">best {{ $assessments->count() }} subj.</div>
    </div>
    <div class="summary-box">
      <div class="s-label">Attendance</div>
      <div class="s-val">{{ $reportCard->attendance_present }}/{{ $reportCard->attendance_total }}</div>
      <div class="s-label">{{ $reportCard->attendance_percentage }}%</div>
    </div>
    <div class="summary-box">
      <div class="s-label">Avg Score</div>
      @php $avg = $assessments->count() ? round($assessments->avg('total_score'), 1) : 0; @endphp
      <div class="s-val">{{ $avg }}</div>
      <div class="s-label">/ 100</div>
    </div>
  </div>

  {{-- ══════════════ GRADE SCALE LEGEND ══════════════ --}}
  <div class="legend">
    @foreach($gradeScale as $band)
    <div class="legend-item">
      <strong class="grade-{{ $band['grade'] }}">{{ $band['grade'] }}</strong>
      {{ $band['min'] }}–{{ $band['max'] }}<br>
      <span style="color:#888;">{{ $band['remark'] }}</span>
    </div>
    @endforeach
  </div>

  {{-- ══════════════ REMARKS + CONDUCT (two-column) ══════════════ --}}
  @php
    $conductRatings = $reportCard->conduct_ratings ?? [];
    $conductTraits  = \App\Models\ReportCard::CONDUCT_TRAITS;
    $ratingClass    = fn($r) => match($r) {
      'Excellent' => 'r-excellent',
      'Very Good' => 'r-very-good',
      'Good'      => 'r-good',
      'Fair'      => 'r-fair',
      'Poor'      => 'r-poor',
      default     => 'r-none',
    };
  @endphp
  <div style="display:table; width:100%; margin-bottom:12px;">

    {{-- Left: remarks --}}
    <div style="display:table-cell; width:58%; padding-right:8px; vertical-align:top;">
      <div class="remarks-section" style="margin-bottom:8px;">
        <h3>Class Teacher's Remarks</h3>
        <div class="remark-box">{{ $reportCard->class_teacher_remark ?: 'No remark entered.' }}</div>
      </div>
      <div class="remarks-section">
        <h3>Head Teacher's Remarks</h3>
        <div class="remark-box">{{ $reportCard->headmaster_remark ?: 'No remark entered.' }}</div>
      </div>
    </div>

    {{-- Right: conduct & behaviour --}}
    <div style="display:table-cell; width:42%; padding-left:8px; vertical-align:top;">
      <div class="conduct-section">
        <h3>Conduct &amp; Behaviour</h3>
        <div class="conduct-grid">
          @foreach($conductTraits as $key => $label)
            @php $rating = $conductRatings[$key] ?? null; @endphp
            <div class="conduct-row">
              <div class="conduct-cell">
                <span class="c-label">{{ $label }}</span>
                <span class="c-rating {{ $ratingClass($rating) }}">
                  {{ $rating ?? '—' }}
                </span>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

  </div>

  {{-- ══════════════ SUBJECT PERFORMANCE BAR CHART ══════════════ --}}
  <div class="barchart-section">
    <h3>Subject Performance</h3>
    @foreach($assessments as $a)
      @php
        $pct   = min(100, max(0, (float)$a->total_score));
        $grade = $a->grade ?? 'F9';
      @endphp
      <div class="bar-row">
        <div class="bar-subject">{{ Str::limit($a->subject?->name ?? '—', 20) }}</div>
        <div class="bar-track">
          <div class="bar-bg">
            <div class="bar-fill bar-{{ $grade }}" style="width:{{ $pct }}%;"></div>
          </div>
        </div>
        <div class="bar-score">{{ number_format($a->total_score, 0) }}</div>
        <div class="bar-grade grade-{{ $grade }}">{{ $grade }}</div>
      </div>
    @endforeach
  </div>

  {{-- ══════════════ NEXT TERM ══════════════ --}}
  <div style="border:1px solid #ddd; border-radius:4px; padding:6px 10px; margin-bottom:12px; font-size:9pt; background:#fafafa;">
    <strong>Next Term Begins:</strong>
    @php
      $nextTerm = \App\Models\AcademicTerm::where('academic_year_id', $term->academic_year_id)
        ->where('term_number', $term->term_number + 1)
        ->first();
    @endphp
    {{ $nextTerm ? $nextTerm->start_date->format('l, d F Y') : 'To be announced' }}
    &nbsp;&nbsp;
    <strong>Promotion Status:</strong>
    {{ $passed >= ceil($assessments->count() * 0.5) ? 'Promoted' : 'Retained — See Headmaster' }}
  </div>

  {{-- ══════════════ SIGNATURES (Class Teacher + Headmaster only) ══════════════ --}}
  <div class="signatures">
    <div class="sig-col">
      <div class="sig-line"></div>
      <div class="sig-label">Class Teacher</div>
      <div class="sig-label" style="color:#333;">{{ $class->classTeacher?->full_name ?? '_______________' }}</div>
    </div>
    <div class="sig-col">
      <div class="sig-line"></div>
      <div class="sig-label">Headmaster / Headmistress</div>
      <div class="sig-label">Signature, Stamp &amp; Date</div>
    </div>
  </div>

  {{-- ══════════════ FOOTER ══════════════ --}}
  <div class="page-footer">
    Generated by SchoolMS Ghana &nbsp;·&nbsp; {{ now()->format('d M Y, H:i') }}
    &nbsp;·&nbsp; This report card is official. Report any discrepancy to the school within 14 days.
  </div>

</div>

@php
function ordinal(int $n): string {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $s[($v-20)%10 ?? $v] ?? $s[0];
}
@endphp
</body>
</html>
