@extends('layouts.admin')

@section('title', 'Feeding Fee Report')
@section('page-title', 'Feeding Fees')

@section('content')

{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  <a href="{{ route('admin.feeding.index') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Fees List</a>
  <a href="{{ route('admin.feeding.config') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Configuration</a>
  <a href="{{ route('admin.feeding.assign') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Bulk Assign</a>
  <a href="{{ route('admin.feeding.report') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Report</a>
</div>

{{-- Term selector --}}
<form method="GET" class="flex items-center gap-3 mb-6">
  <label class="text-sm font-medium text-gray-700">Term:</label>
  <select name="term_id" onchange="this.form.submit()"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    @foreach($terms as $t)
      <option value="{{ $t->id }}" {{ $selectedTermId == $t->id ? 'selected' : '' }}>
        {{ $t->term_name }} {{ $t->academicYear?->year_label ? '(' . $t->academicYear->year_label . ')' : '' }}
        {{ $t->is_current ? '— Current' : '' }}
      </option>
    @endforeach
  </select>
</form>

@if($stats && $term)

{{-- ── Summary Stats ──────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</div>
    <div class="text-xs text-gray-500 mt-1">Students Billed</div>
  </div>
  <div class="bg-green-50 rounded-xl border border-green-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-bold text-green-700">GHS {{ number_format($stats['collected'], 2) }}</div>
    <div class="text-xs text-gray-500 mt-1">Total Collected</div>
  </div>
  <div class="bg-red-50 rounded-xl border border-red-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-bold text-red-700">GHS {{ number_format($stats['outstanding'], 2) }}</div>
    <div class="text-xs text-gray-500 mt-1">Outstanding</div>
  </div>
  <div class="bg-gray-50 rounded-xl border border-gray-100 shadow-sm p-4 text-center">
    <div class="text-2xl font-bold text-gray-700">GHS {{ number_format($stats['expected'], 2) }}</div>
    <div class="text-xs text-gray-500 mt-1">Expected Total</div>
  </div>
</div>

{{-- ── Collection Rate Bar ──────────────────────────────────────────── --}}
@php
  $rate = $stats['expected'] > 0 ? round($stats['collected'] / $stats['expected'] * 100) : 0;
@endphp
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-6">
  <div class="flex items-center justify-between mb-2">
    <span class="text-sm font-medium text-gray-700">Collection Rate</span>
    <span class="text-sm font-bold {{ $rate >= 80 ? 'text-green-600' : ($rate >= 50 ? 'text-amber-600' : 'text-red-600') }}">
      {{ $rate }}%
    </span>
  </div>
  <div class="w-full bg-gray-100 rounded-full h-3">
    <div class="h-3 rounded-full transition-all {{ $rate >= 80 ? 'bg-green-500' : ($rate >= 50 ? 'bg-amber-500' : 'bg-red-500') }}"
         style="width: {{ $rate }}%"></div>
  </div>
  <div class="flex justify-between mt-2 text-xs text-gray-400">
    <span>{{ $stats['paid'] }} fully paid · {{ $stats['partial'] }} partial · {{ $stats['unpaid'] }} unpaid · {{ $stats['exempt'] }} exempt</span>
  </div>
</div>

{{-- ── Class Breakdown ───────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
  <div class="px-5 py-4 border-b border-gray-100">
    <h3 class="text-sm font-semibold text-gray-900">Per-Class Breakdown — {{ $term->term_name }}</h3>
  </div>
  <table class="min-w-full divide-y divide-gray-100">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Students</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Partial</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Unpaid</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Expected</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Collected</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($breakdown as $row)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row->class_name }}</td>
          <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $row->total }}</td>
          <td class="px-4 py-3 text-sm text-right text-green-600 font-medium">{{ $row->paid }}</td>
          <td class="px-4 py-3 text-sm text-right text-amber-600">{{ $row->partial }}</td>
          <td class="px-4 py-3 text-sm text-right text-red-600">{{ $row->unpaid }}</td>
          <td class="px-4 py-3 text-sm text-right text-gray-700">GHS {{ number_format($row->expected, 2) }}</td>
          <td class="px-4 py-3 text-sm text-right text-green-700 font-semibold">GHS {{ number_format($row->collected, 2) }}</td>
          <td class="px-4 py-3 text-sm text-right text-red-700">GHS {{ number_format(max(0, $row->expected - $row->collected), 2) }}</td>
          <td class="px-4 py-3 text-right">
            <a href="{{ route('admin.feeding.index', ['term_id' => $selectedTermId, 'class_id' => $row->class_id]) }}"
               class="text-blue-600 hover:text-blue-700 text-xs">View →</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">
            No feeding fees assigned for this term.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- ── Recent Payments ──────────────────────────────────────────────── --}}
@if($recentPayments->isNotEmpty())
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100">
    <h3 class="text-sm font-semibold text-gray-900">Recent Payments (last 50)</h3>
  </div>
  <table class="min-w-full divide-y divide-gray-100">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Receipt</th>
        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
        <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
        <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
        <th class="px-4 py-2.5"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @foreach($recentPayments as $p)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2 text-xs font-mono text-gray-600">{{ $p->receipt_number }}</td>
          <td class="px-4 py-2 text-sm text-gray-700">{{ $p->student?->full_name }}</td>
          <td class="px-4 py-2 text-xs text-gray-500">{{ $p->feedingFee?->schoolClass?->name }}</td>
          <td class="px-4 py-2 text-xs text-gray-500">{{ $p->methodLabel() }}</td>
          <td class="px-4 py-2 text-xs text-gray-500">{{ $p->payment_date->format('d M Y') }}</td>
          <td class="px-4 py-2 text-sm text-right font-semibold text-green-700">GHS {{ number_format($p->amount, 2) }}</td>
          <td class="px-4 py-2 text-right">
            <a href="{{ route('admin.feeding.receipt', $p->id) }}" target="_blank"
               class="text-blue-600 text-xs hover:underline">Receipt</a>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

@else
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-12 text-center">
  <p class="text-gray-400 text-sm">Select a term to view the feeding fee report.</p>
</div>
@endif

@endsection
