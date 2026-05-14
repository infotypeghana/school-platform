<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; background: #fff; padding: 32px; }

    .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #1d4ed8; padding-bottom: 16px; }
    .school-name { font-size: 20px; font-weight: 700; color: #1e3a5f; }
    .doc-title { font-size: 14px; font-weight: 600; color: #3b82f6; margin-top: 4px; }
    .doc-subtitle { font-size: 11px; color: #6b7280; margin-top: 2px; }

    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px; }
    .kpi-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; text-align: center; }
    .kpi-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-value { font-size: 15px; font-weight: 700; margin-top: 2px; }
    .green  { color: #059669; } .red { color: #dc2626; } .blue { color: #2563eb; } .gray { color: #374151; }
    .bg-green-light { background: #ecfdf5; } .bg-red-light { background: #fef2f2; }
    .bg-blue-light  { background: #eff6ff; } .bg-gray-light  { background: #f9fafb; }

    h3 { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 8px; margin-top: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }

    table { width: 100%; border-collapse: collapse; }
    th { background: #f3f4f6; text-align: left; font-size: 9px; font-weight: 700; text-transform: uppercase; color: #6b7280; padding: 7px 10px; }
    th.right, td.right { text-align: right; }
    td { padding: 7px 10px; border-bottom: 1px solid #f3f4f6; }
    tr:last-child td { border-bottom: none; }

    .surplus-box { margin-top: 20px; border: 2px solid; border-radius: 8px; padding: 14px; text-align: center; }
    .surplus-pos { border-color: #34d399; background: #ecfdf5; }
    .surplus-neg { border-color: #f87171; background: #fef2f2; }
    .surplus-label { font-size: 11px; font-weight: 600; color: #374151; }
    .surplus-value { font-size: 22px; font-weight: 700; margin-top: 4px; }

    .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; font-size: 9px; color: #9ca3af; }

    .sig-row { display: flex; justify-content: space-between; margin-top: 40px; }
    .sig-box { text-align: center; width: 30%; }
    .sig-line { border-top: 1px solid #374151; padding-top: 6px; font-size: 10px; font-weight: 600; color: #374151; }
    .sig-title { font-size: 9px; color: #6b7280; margin-top: 2px; }
  </style>
</head>
<body>

  <div class="header">
    <div class="school-name">{{ $tenant?->name ?? 'School Name' }}</div>
    @if($tenant?->address)
      <div class="doc-subtitle">{{ $tenant->address }}</div>
    @endif
    <div class="doc-title">Financial Statement</div>
    <div class="doc-subtitle">
      {{ $term ? $term->term_name . ' · ' . $term->academicYear?->year_label : 'All Periods' }}
      &nbsp;·&nbsp; Generated {{ now()->format('d M Y') }}
    </div>
  </div>

  {{-- KPIs --}}
  <div class="kpi-grid">
    <div class="kpi-box bg-gray-light">
      <div class="kpi-label">Total Billed</div>
      <div class="kpi-value gray">GHS {{ number_format($totalBilled, 2) }}</div>
    </div>
    <div class="kpi-box bg-green-light">
      <div class="kpi-label">Collected</div>
      <div class="kpi-value green">GHS {{ number_format($totalPaid, 2) }}</div>
    </div>
    <div class="kpi-box bg-red-light">
      <div class="kpi-label">Outstanding</div>
      <div class="kpi-value red">GHS {{ number_format($totalOwed, 2) }}</div>
    </div>
    <div class="kpi-box bg-red-light">
      <div class="kpi-label">Total Expenses</div>
      <div class="kpi-value red">GHS {{ number_format($totalExpenses, 2) }}</div>
    </div>
  </div>

  {{-- Income breakdown --}}
  <h3>Income Breakdown</h3>
  <table>
    <thead>
      <tr>
        <th>Fee Type</th>
        <th class="right">Billed (GHS)</th>
        <th class="right">Collected (GHS)</th>
        <th class="right">Outstanding (GHS)</th>
        <th class="right">Collection Rate</th>
      </tr>
    </thead>
    <tbody>
      @forelse($incomeByType as $row)
        @php $rate = $row->billed > 0 ? round(($row->collected / $row->billed) * 100) : 0; @endphp
        <tr>
          <td>{{ $row->fee_type }}</td>
          <td class="right">{{ number_format($row->billed, 2) }}</td>
          <td class="right" style="color:#059669;font-weight:600;">{{ number_format($row->collected, 2) }}</td>
          <td class="right" style="color:#dc2626;">{{ number_format($row->billed - $row->collected, 2) }}</td>
          <td class="right">{{ $rate }}%</td>
        </tr>
      @empty
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:16px;">No income records.</td></tr>
      @endforelse
      @if($incomeByType->isNotEmpty())
        <tr style="background:#f3f4f6;font-weight:700;">
          <td>TOTAL</td>
          <td class="right">{{ number_format($totalBilled, 2) }}</td>
          <td class="right" style="color:#059669;">{{ number_format($totalPaid, 2) }}</td>
          <td class="right" style="color:#dc2626;">{{ number_format($totalOwed, 2) }}</td>
          <td class="right">{{ $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100) : 0 }}%</td>
        </tr>
      @endif
    </tbody>
  </table>

  {{-- Expenses breakdown --}}
  <h3>Expenditure Breakdown</h3>
  <table>
    <thead>
      <tr>
        <th>Category</th>
        <th class="right">Amount (GHS)</th>
        <th class="right">% of Total</th>
      </tr>
    </thead>
    <tbody>
      @forelse($expenseByCategory as $row)
        @php
          $pct   = $totalExpenses > 0 ? round(($row->total / $totalExpenses) * 100, 1) : 0;
          $label = \App\Models\Expense::CATEGORIES[$row->category] ?? ucfirst($row->category);
        @endphp
        <tr>
          <td>{{ $label }}</td>
          <td class="right" style="color:#dc2626;">{{ number_format($row->total, 2) }}</td>
          <td class="right">{{ $pct }}%</td>
        </tr>
      @empty
        <tr><td colspan="3" style="text-align:center;color:#9ca3af;padding:16px;">No expense records.</td></tr>
      @endforelse
      @if($expenseByCategory->isNotEmpty())
        <tr style="background:#f3f4f6;font-weight:700;">
          <td>TOTAL</td>
          <td class="right" style="color:#dc2626;">{{ number_format($totalExpenses, 2) }}</td>
          <td class="right">100%</td>
        </tr>
      @endif
    </tbody>
  </table>

  {{-- Net surplus / deficit --}}
  <div class="surplus-box {{ $netSurplus >= 0 ? 'surplus-pos' : 'surplus-neg' }}">
    <div class="surplus-label">{{ $netSurplus >= 0 ? 'Net Surplus' : 'Net Deficit' }}</div>
    <div class="surplus-value {{ $netSurplus >= 0 ? 'green' : 'red' }}">
      GHS {{ number_format(abs($netSurplus), 2) }}
    </div>
    <div style="font-size:9px;color:#6b7280;margin-top:4px;">
      Income Collected ({{ number_format($totalPaid, 2) }}) − Total Expenses ({{ number_format($totalExpenses, 2) }})
    </div>
  </div>

  {{-- Signatures --}}
  <div class="sig-row">
    <div class="sig-box">
      <div class="sig-line">Headteacher / Principal</div>
      <div class="sig-title">Signature &amp; Date</div>
    </div>
    <div class="sig-box">
      <div class="sig-line">Finance Officer</div>
      <div class="sig-title">Signature &amp; Date</div>
    </div>
    <div class="sig-box">
      <div class="sig-line">School Stamp</div>
      <div class="sig-title">&nbsp;</div>
    </div>
  </div>

  <div class="footer">
    <span>{{ $tenant?->name }} — Confidential Financial Record</span>
    <span>SchoolMS Ghana · schoolms.com.gh</span>
  </div>

</body>
</html>
