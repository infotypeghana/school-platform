@extends('layouts.admin')

@section('title', 'Financial Statements')
@section('page-title', 'Financial Statements')

@section('content')

{{-- Term filter --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <form method="GET" class="flex gap-2">
    <select name="term_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All-time</option>
      @foreach($terms as $t)
        <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>
          {{ $t->term_name }} · {{ $t->academicYear?->year_label }}
        </option>
      @endforeach
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <div class="flex gap-2">
    <a href="{{ route('admin.finance.ledger') . ($termId ? '?term_id=' . $termId : '') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      Ledger View
    </a>
    <a href="{{ route('admin.finance.statement') . ($termId ? '?term_id=' . $termId : '') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-purple-300 text-purple-700 text-sm font-medium hover:bg-purple-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      PDF Statement
    </a>
    <a href="{{ route('admin.finance.expenses.create') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      + Record Expense
    </a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

{{-- P&L Summary --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  @foreach([
    ['Total Billed',   $totalBilled,    'text-gray-900',    'bg-gray-50'],
    ['Total Collected',$totalPaid,      'text-emerald-700', 'bg-emerald-50'],
    ['Outstanding',    $totalOwed,      'text-red-700',     'bg-red-50'],
    ['Net Surplus',    $netSurplus,     $netSurplus >= 0 ? 'text-blue-700' : 'text-red-700', $netSurplus >= 0 ? 'bg-blue-50' : 'bg-red-50'],
  ] as [$label, $val, $color, $bg])
    <div class="rounded-xl border border-gray-100 shadow-sm p-4 {{ $bg }}">
      <p class="text-xs font-medium text-gray-500 mb-1">{{ $label }}</p>
      <p class="text-2xl font-bold {{ $color }}">GHS {{ number_format($val, 2) }}</p>
    </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">

  {{-- Income by fee type --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="text-sm font-semibold text-gray-700">Income by Fee Type</h3>
    </div>
    @if($incomeByType->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">No income records{{ $termId ? ' for this term' : '' }}.</p>
    @else
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Fee Type</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Billed</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Collected</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Rate</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($incomeByType as $row)
            @php $rate = $row->billed > 0 ? round(($row->collected / $row->billed) * 100) : 0; @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-700 font-medium">{{ $row->fee_type }}</td>
              <td class="px-4 py-2 text-right text-gray-600">{{ number_format($row->billed, 2) }}</td>
              <td class="px-4 py-2 text-right text-emerald-700 font-medium">{{ number_format($row->collected, 2) }}</td>
              <td class="px-4 py-2 text-right">
                <span class="text-xs font-semibold {{ $rate >= 80 ? 'text-emerald-600' : ($rate >= 50 ? 'text-amber-600' : 'text-red-500') }}">
                  {{ $rate }}%
                </span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  {{-- Expenses by category --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">Expenses by Category</h3>
      <span class="text-sm font-bold text-red-700">GHS {{ number_format($totalExpenses, 2) }}</span>
    </div>
    @if($expenseByCategory->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">No expenses recorded{{ $termId ? ' for this term' : '' }}.</p>
    @else
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Category</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-28">Share</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($expenseByCategory as $row)
            @php
              $pct = $totalExpenses > 0 ? round(($row->total / $totalExpenses) * 100) : 0;
              $label = \App\Models\Expense::CATEGORIES[$row->category] ?? ucfirst($row->category);
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-700 font-medium">{{ $label }}</td>
              <td class="px-4 py-2 text-right text-red-600 font-medium">{{ number_format($row->total, 2) }}</td>
              <td class="px-4 py-2">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-2 rounded-full bg-gray-100">
                    <div class="h-2 rounded-full bg-red-400" style="width: {{ $pct }}%"></div>
                  </div>
                  <span class="text-xs text-gray-400 w-8 text-right">{{ $pct }}%</span>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>

{{-- Recent expenses --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
    <h3 class="text-sm font-semibold text-gray-700">Recent Expenses</h3>
    <a href="{{ route('admin.finance.expenses.create') }}"
       class="text-xs text-blue-600 hover:underline">+ Add</a>
  </div>
  @if($recentExpenses->isEmpty())
    <p class="px-5 py-8 text-sm text-gray-400 text-center">No expenses recorded yet.</p>
  @else
    <table class="w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Date</th>
          <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Description</th>
          <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Category</th>
          <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount</th>
          <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        @foreach($recentExpenses as $expense)
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2 text-gray-500 text-xs">{{ $expense->date->format('d M Y') }}</td>
            <td class="px-4 py-2 text-gray-700">{{ $expense->description }}</td>
            <td class="px-4 py-2 text-xs text-gray-500">{{ $expense->category_label }}</td>
            <td class="px-4 py-2 text-right font-medium text-red-600">GHS {{ number_format($expense->amount, 2) }}</td>
            <td class="px-4 py-2 text-right">
              <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.finance.expenses.edit', $expense) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
                <form method="POST" action="{{ route('admin.finance.expenses.destroy', $expense) }}"
                      onsubmit="return confirm('Delete this expense?')" class="inline">
                  @csrf @method('DELETE')
                  <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

@endsection
