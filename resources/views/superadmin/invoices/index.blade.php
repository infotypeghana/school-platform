@extends('layouts.superadmin')

@section('title', 'Invoices')
@section('page-title', 'Invoices')

@section('content')

{{-- Filters --}}
<form method="GET" class="flex flex-wrap gap-3 mb-6">
  <input name="search" value="{{ request('search') }}"
         placeholder="School or invoice #…"
         class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-60 focus:outline-none focus:ring-2 focus:ring-blue-500">
  <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
    <option value="">All statuses</option>
    @foreach(['draft','sent','paid','void'] as $s)
      <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
    @endforeach
  </select>
  <button class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
  @if(request()->hasAny(['search','status']))
    <a href="{{ route('superadmin.invoices') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">Clear</a>
  @endif
</form>

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Invoice #</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">School</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Term</th>
        <th class="text-right px-4 py-3 font-semibold text-gray-600">Amount</th>
        <th class="text-center px-4 py-3 font-semibold text-gray-600">Status</th>
        <th class="text-left px-4 py-3 font-semibold text-gray-600">Due</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($invoices as $inv)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $inv->invoice_number }}</td>
          <td class="px-4 py-3 font-medium text-gray-800">{{ $inv->tenant?->name ?? '—' }}</td>
          <td class="px-4 py-3 text-gray-500 text-xs">{{ $inv->term?->term_name ?? '—' }}</td>
          <td class="px-4 py-3 text-right font-semibold text-gray-800">GHS {{ number_format($inv->amount, 2) }}</td>
          <td class="px-4 py-3 text-center">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $inv->statusBadgeClass() }}">
              {{ $inv->statusLabel() }}
            </span>
          </td>
          <td class="px-4 py-3 text-gray-500 text-xs">{{ $inv->due_date->format('d M Y') }}</td>
          <td class="px-4 py-3 text-right">
            <a href="{{ route('superadmin.invoices.show', $inv) }}"
               class="text-blue-600 hover:text-blue-700 text-xs font-medium">View</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No invoices found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($invoices->hasPages())
  <div class="mt-4">{{ $invoices->links() }}</div>
@endif

@endsection
