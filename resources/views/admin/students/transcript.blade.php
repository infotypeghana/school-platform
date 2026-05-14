<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }
  .page { padding: 30px 36px; }

  /* Header */
  .header { text-align: center; border-bottom: 3px double #1d4ed8; padding-bottom: 14px; margin-bottom: 18px; }
  .school-name { font-size: 17px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px; }
  .school-meta { font-size: 9px; color: #6b7280; margin-top: 4px; }
  .doc-title { font-size: 13px; font-weight: 700; color: #374151; margin-top: 8px; text-transform: uppercase; letter-spacing: 2px; }

  /* Student info bar */
  .student-bar { background: #f0f4ff; border: 1px solid #c7d2fe; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px; }
  .student-bar table { width: 100%; }
  .student-bar td { padding: 2px 6px; font-size: 10px; }
  .student-bar .label { color: #6b7280; width: 110px; }
  .student-bar .value { font-weight: 600; color: #111827; }

  /* Term section */
  .term-header { background: #1d4ed8; color: #fff; padding: 6px 10px; font-size: 10px; font-weight: 700;
                 letter-spacing: 0.5px; margin-top: 14px; border-radius: 4px 4px 0 0; }
  table.results { width: 100%; border-collapse: collapse; margin-bottom: 0; }
  table.results th { background: #e0e7ff; color: #1e3a8a; padding: 6px 8px; font-size: 9px;
                     text-transform: uppercase; letter-spacing: 0.4px; border: 1px solid #c7d2fe; }
  table.results td { padding: 5px 8px; border: 1px solid #e5e7eb; font-size: 10px; }
  table.results tr:nth-child(even) td { background: #f9fafb; }
  .grade { font-weight: 700; text-align: center; }
  .grade-a { color: #059669; }
  .grade-b { color: #2563eb; }
  .grade-c { color: #d97706; }
  .grade-f { color: #dc2626; }
  td.num { text-align: center; }

  /* Term summary row */
  .term-summary { background: #f0f4ff; }
  .term-summary td { padding: 5px 8px; font-weight: 600; font-size: 10px; border: 1px solid #c7d2fe; }

  /* Footer */
  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 14px; display: flex; justify-content: space-between; }
  .sig-block { width: 180px; }
  .sig-line { border-bottom: 1px dashed #9ca3af; margin-top: 28px; }
  .sig-label { font-size: 8px; color: #9ca3af; margin-top: 3px; }
  .watermark { font-size: 8px; color: #9ca3af; text-align: center; margin-top: 14px; }
</style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="header">
    <div class="school-name">{{ $tenant->name }}</div>
    <div class="school-meta">
      {{ $tenant->address ?? '' }}
      @if($tenant->contact_phone) · Tel: {{ $tenant->contact_phone }} @endif
    </div>
    <div class="doc-title">Academic Transcript</div>
  </div>

  {{-- Student bar --}}
  <div class="student-bar">
    <table>
      <tr>
        <td class="label">Student Name:</td>
        <td class="value">{{ $student->full_name }}</td>
        <td class="label">Admission No:</td>
        <td class="value">{{ $student->admission_number ?? '—' }}</td>
        <td class="label">Date Issued:</td>
        <td class="value">{{ now()->format('d M Y') }}</td>
      </tr>
      <tr>
        <td class="label">Class:</td>
        <td class="value">{{ $student->schoolClass?->full_name ?? '—' }}</td>
        <td class="label">Gender:</td>
        <td class="value">{{ ucfirst($student->gender ?? '—') }}</td>
        <td class="label">Status:</td>
        <td class="value">{{ ucfirst($student->status) }}</td>
      </tr>
    </table>
  </div>

  {{-- Results by term --}}
  @forelse($byTerm as $termId => $assessments)
    @php $term = $assessments->first()->term; @endphp
    <div class="term-header">
      {{ $term->term_name ?? 'Term' }}
      @if($term->academicYear) · {{ $term->academicYear->year_label }} @endif
    </div>
    <table class="results">
      <thead>
        <tr>
          <th style="text-align:left; width:40%">Subject</th>
          <th>CA (30)</th>
          <th>Exam (70)</th>
          <th>Total (100)</th>
          <th>Grade</th>
          <th style="text-align:left">Remark</th>
          <th>Position</th>
        </tr>
      </thead>
      <tbody>
        @foreach($assessments as $a)
          @php
            $g = $a->grade ?? '';
            $gc = str_starts_with($g,'A') ? 'grade-a' : (str_starts_with($g,'B') ? 'grade-b' : (str_starts_with($g,'C') ? 'grade-c' : (str_starts_with($g,'F') ? 'grade-f' : '')));
          @endphp
          <tr>
            <td>{{ $a->subject?->name ?? '—' }}</td>
            <td class="num">{{ number_format($a->ca_score, 1) }}</td>
            <td class="num">{{ number_format($a->exam_score, 1) }}</td>
            <td class="num" style="font-weight:600">{{ number_format($a->total_score, 1) }}</td>
            <td class="grade {{ $gc }}">{{ $g }}</td>
            <td>{{ $a->remark }}</td>
            <td class="num">{{ $a->position_in_class ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        @php
          $avg = $assessments->avg('total_score');
          $count = $assessments->count();
        @endphp
        <tr class="term-summary">
          <td>{{ $count }} subject(s)</td>
          <td class="num">{{ number_format($assessments->avg('ca_score'), 1) }}</td>
          <td class="num">{{ number_format($assessments->avg('exam_score'), 1) }}</td>
          <td class="num" colspan="4">Average Total: <strong>{{ number_format($avg, 1) }}</strong></td>
        </tr>
      </tfoot>
    </table>
  @empty
    <p style="color:#6b7280; font-size:11px; margin-top:16px;">No assessment records found for this student.</p>
  @endforelse

  {{-- Footer --}}
  <div class="footer">
    <div class="sig-block">
      <div class="sig-line"></div>
      <div class="sig-label">Class Teacher's Signature</div>
    </div>
    <div class="sig-block" style="text-align:center;">
      <div class="sig-line"></div>
      <div class="sig-label">Headteacher's Signature</div>
    </div>
    <div class="sig-block" style="text-align:right;">
      <div class="sig-line"></div>
      <div class="sig-label">School Stamp / Date</div>
    </div>
  </div>
  <div class="watermark">{{ $tenant->name }} · Official Academic Transcript · Generated {{ now()->format('d M Y H:i') }}</div>

</div>
</body>
</html>
