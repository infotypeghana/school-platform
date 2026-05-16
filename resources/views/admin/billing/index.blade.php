@extends('layouts.admin')

@section('title', 'Billing')
@section('page-title', 'Billing & Invoices')

@section('content')

{{-- Outstanding balance --}}
@if($outstanding > 0)
  <div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-4">
    <div class="text-amber-500">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
      </svg>
    </div>
    <div class="flex-1">
      <p class="font-semibold text-amber-800 text-sm">Outstanding balance: GHS {{ number_format($outstanding, 2) }}</p>
      <p class="text-amber-600 text-xs mt-0.5">Please settle your invoices to avoid service interruption.</p>
    </div>
  </div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Invoice #</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Term</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Package</th>
        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
        <th class="text-center px-4 py-3 font-semibold text-gray-600">Status</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Due</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($invoices as $inv)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $inv->invoice_number }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $inv->term?->term_name ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-600">{{ $inv->package_name }}</td>
          <td class="px-4 py-3 text-right font-semibold text-gray-800">GHS {{ number_format($inv->amount, 2) }}</td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $inv->statusBadgeClass() }}">
              {{ $inv->statusLabel() }}
            </span>
          </td>
          <td class="px-4 py-3 text-gray-500 text-xs">{{ $inv->due_date->format('d M Y') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No invoices yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
