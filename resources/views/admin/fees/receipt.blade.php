<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
  .page { padding: 36px 40px; max-width: 620px; margin: 0 auto; }

  /* Header */
  .header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 16px; border-bottom: 2px solid #1d4ed8; margin-bottom: 20px; }
  .school-name { font-size: 18px; font-weight: 700; color: #1d4ed8; }
  .school-meta { font-size: 10px; color: #6b7280; margin-top: 3px; }
  .receipt-badge { text-align: right; }
  .receipt-badge .title { font-size: 14px; font-weight: 700; color: #059669; letter-spacing: 1px; text-transform: uppercase; }
  .receipt-badge .number { font-size: 12px; font-weight: 600; color: #374151; margin-top: 2px; }
  .receipt-badge .date { font-size: 10px; color: #6b7280; margin-top: 1px; }

  /* Paid stamp */
  .stamp-wrap { text-align: right; margin-bottom: 16px; }
  .stamp { display: inline-block; border: 3px solid #059669; color: #059669; padding: 4px 12px;
           font-size: 13px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase;
           transform: rotate(-6deg); opacity: 0.85; }
  .stamp.partial { border-color: #d97706; color: #d97706; }
  .stamp.unpaid  { border-color: #dc2626; color: #dc2626; }

  /* Student info */
  .section-title { font-size: 9px; font-weight: 700; color: #6b7280; text-transform: uppercase;
                   letter-spacing: 0.8px; margin-bottom: 8px; }
  .info-grid { display: flex; gap: 0; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; margin-bottom: 20px; }
  .info-cell { flex: 1; padding: 10px 14px; border-right: 1px solid #e5e7eb; }
  .info-cell:last-child { border-right: none; }
  .info-label { font-size: 9px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; }
  .info-value { font-size: 12px; font-weight: 600; color: #111827; margin-top: 2px; }

  /* Fee table */
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  thead th { background: #1d4ed8; color: #fff; padding: 8px 12px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
  thead th:last-child { text-align: right; }
  tbody td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
  tbody td:last-child { text-align: right; font-weight: 600; }
  tfoot td { padding: 8px 12px; font-size: 11px; }
  .total-row td { border-top: 2px solid #1d4ed8; font-weight: 700; color: #1d4ed8; font-size: 13px; }
  .balance-row td { color: #dc2626; font-weight: 600; }
  .balance-row.zero td { color: #059669; }

  /* Footer */
  .footer { border-top: 1px solid #e5e7eb; padding-top: 14px; margin-top: 8px; }
  .sig-line { border-bottom: 1px dashed #9ca3af; width: 160px; margin-top: 28px; }
  .sig-label { font-size: 9px; color: #9ca3af; margin-top: 3px; }
  .note { font-size: 9px; color: #9ca3af; text-align: center; margin-top: 16px; }
</style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="header">
    <div>
      <div class="school-name">{{ $tenant->name }}</div>
      <div class="school-meta">
        @if($tenant->address) {{ $tenant->address }}<br>@endif
        @if($tenant->contact_phone) Tel: {{ $tenant->contact_phone }} @endif
        @if($tenant->contact_email) · {{ $tenant->contact_email }} @endif
      </div>
    </div>
    <div class="receipt-badge">
      <div class="title">Official Receipt</div>
      <div class="number">{{ $fee->receipt_number }}</div>
      <div class="date">{{ now()->format('d M Y') }}</div>
    </div>
  </div>

  {{-- Paid stamp --}}
  <div class="stamp-wrap">
    <span class="stamp {{ $fee->status === 'partial' ? 'partial' : ($fee->status === 'unpaid' ? 'unpaid' : '') }}">
      {{ strtoupper($fee->status) }}
    </span>
  </div>

  {{-- Student info --}}
  <div class="section-title">Student Details</div>
  <div class="info-grid">
    <div class="info-cell">
      <div class="info-label">Student</div>
      <div class="info-value">{{ $fee->student->full_name }}</div>
    </div>
    <div class="info-cell">
      <div class="info-label">Admission No.</div>
      <div class="info-value">{{ $fee->student->admission_number ?? '—' }}</div>
    </div>
    <div class="info-cell">
      <div class="info-label">Class</div>
      <div class="info-value">{{ $fee->student->schoolClass?->full_name ?? '—' }}</div>
    </div>
    <div class="info-cell">
      <div class="info-label">Term</div>
      <div class="info-value">{{ $fee->term->term_name }}</div>
    </div>
  </div>

  {{-- Fee breakdown --}}
  <div class="section-title">Payment Details</div>
  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th>Due Date</th>
        <th>Amount (GHS)</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>{{ $fee->fee_type }}</td>
        <td>{{ $fee->due_date?->format('d M Y') ?? '—' }}</td>
        <td>{{ number_format($fee->amount, 2) }}</td>
      </tr>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2" style="text-align:right; font-size:10px; color:#6b7280;">Amount Levied:</td>
        <td style="text-align:right;">{{ number_format($fee->amount, 2) }}</td>
      </tr>
      <tr class="total-row">
        <td colspan="2" style="text-align:right;">Amount Paid:</td>
        <td style="text-align:right;">GHS {{ number_format($fee->amount_paid, 2) }}</td>
      </tr>
      @if($fee->balance > 0)
      <tr class="balance-row">
        <td colspan="2" style="text-align:right;">Outstanding Balance:</td>
        <td style="text-align:right;">GHS {{ number_format($fee->balance, 2) }}</td>
      </tr>
      @else
      <tr class="balance-row zero">
        <td colspan="2" style="text-align:right;">Balance:</td>
        <td style="text-align:right;">GHS 0.00 ✓</td>
      </tr>
      @endif
    </tfoot>
  </table>

  {{-- Footer --}}
  <div class="footer" style="display:flex; justify-content:space-between; align-items:flex-end;">
    <div>
      <div class="sig-line"></div>
      <div class="sig-label">Authorised Signature</div>
    </div>
    <div style="text-align:right;">
      <div class="sig-line" style="margin-left:auto;"></div>
      <div class="sig-label">School Stamp / Date</div>
    </div>
  </div>
  <div class="note">This is a computer-generated receipt. Retain for your records. · {{ $tenant->name }} · {{ now()->year }}</div>

</div>
</body>
</html>
