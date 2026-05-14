@extends('layouts.admin')

@section('title', 'Transaction Ledger')
@section('page-title', 'Transaction Ledger')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <form method="GET" class="flex flex-wrap gap-2">
    <select name="term_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All periods</option>
      @foreach($terms as $t)
        <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>
          {{ $t->term_name }} · {{ $t->academicYear?->year_label }}
        </option>
      @endforeach
    </select>
    <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All types</option>
      <option value="income"  {{ $type === 'income'  ? 'selected' : '' }}>Income</option>
      <option value="expense" {{ $type === 'expense' ? 'selected' : '' }}>Expenses</option>
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <a href="{{ route('admin.finance.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Summary</a>
</div>

{{-- Totals bar --}}
<div class="grid grid-cols-3 gap-4 mb-5">
  <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-4">
    <p class="text-xs font-medium text-gray-500 mb-1">Total Income</p>
    <p class="text-xl font-bold text-emerald-700">GHS {{ number_format($totals['credit'] ?? 0, 2) }}</p>
  </div>
  <div class="rounded-xl bg-red-50 border border-red-100 p-4">
    <p class="text-xs font-medium text-gray-500 mb-1">Total Expenses</p>
    <p class="text-xl font-bold text-red-700">GHS {{ number_format($totals['debit'] ?? 0, 2) }}</p>
  </div>
  <div class="rounded-xl {{ ($totals['net'] ?? 0) >= 0 ? 'bg-blue-50 border-blue-100' : 'bg-orange-50 border-orange-100' }} border p-4">
    <p class="text-xs font-medium text-gray-500 mb-1">Net</p>
    <p class="text-xl font-bold {{ ($totals['net'] ?? 0) >= 0 ? 'text-blue-700' : 'text-orange-700' }}">
      GHS {{ number_format(abs($totals['net'] ?? 0), 2) }}
      {{ ($totals['net'] ?? 0) >= 0 ? 'surplus' : 'deficit' }}
    </p>
  </div>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Description</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Reference</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600 text-emerald-700">Income</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600 text-red-600">Expense</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($entries as $entry)
        <tr class="hover:bg-gray-50 {{ $entry['type'] === 'expense' ? 'bg-red-50/30' : '' }}">
          <td class="px-4 py-2.5 text-xs text-gray-500 font-mono">{{ $entry['date'] }}</td>
          <td class="px-4 py-2.5 text-gray-700">{{ $entry['description'] }}</td>
          <td class="px-4 py-2.5 text-xs text-gray-400 font-mono">{{ $entry['reference'] ?? '—' }}</td>
          <td class="px-4 py-2.5 text-right text-emerald-700 font-medium">
            {{ $entry['credit'] !== null ? 'GHS ' . number_format($entry['credit'], 2) : '' }}
          </td>
          <td class="px-4 py-2.5 text-right text-red-600 font-medium">
            {{ $entry['debit'] !== null ? 'GHS ' . number_format($entry['debit'], 2) : '' }}
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">No transactions found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
