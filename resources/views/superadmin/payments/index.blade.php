@extends('layouts.superadmin')

@section('title', 'Payments')
@section('page-title', 'Payments')

@section('content')

{{-- Stats --}}
<div class="mb-4 bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-6 text-sm">
  <div>
    <span class="text-gray-400">Total Revenue: </span>
    <span class="font-bold text-emerald-700 text-lg">GHS {{ number_format($totalRevenue, 2) }}</span>
  </div>
  <div>
    <span class="text-gray-400">Shown: </span>
    <span class="font-semibold">{{ $payments->total() }}</span>
  </div>
</div>

{{-- Search / Filter --}}
<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input type="text" name="search" value="{{ request('search') }}"
         placeholder="Search school or reference…"
         class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 w-64">
  <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All statuses</option>
    <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
    <option value="failed"  {{ request('status') === 'failed'  ? 'selected' : '' }}>Failed</option>
  </select>
  <select name="gateway" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All gateways</option>
    <option value="paystack" {{ request('gateway') === 'paystack' ? 'selected' : '' }}>Paystack</option>
    <option value="moolre"   {{ request('gateway') === 'moolre'   ? 'selected' : '' }}>Moolre</option>
  </select>
  <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
    Filter
  </button>
  @if(request()->hasAny(['search', 'status', 'gateway']))
    <a href="{{ route('superadmin.payments') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
      Clear
    </a>
  @endif
</form>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">School</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Reference</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Gateway</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Amount</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Date</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($payments as $payment)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2.5 font-medium text-gray-800">{{ $payment->tenant?->name }}</td>
          <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ Str::limit($payment->reference, 24) }}</td>
          <td class="px-4 py-2.5 text-gray-600 capitalize text-xs">{{ $payment->gateway }}</td>
          <td class="px-4 py-2.5 text-right font-semibold text-gray-800">GHS {{ number_format($payment->amount, 2) }}</td>
          <td class="px-4 py-2.5 text-center">
            @php $b = match($payment->status) { 'success' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', default => 'bg-red-100 text-red-600' }; @endphp
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $b }}">{{ ucfirst($payment->status) }}</span>
          </td>
          <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $payment->created_at->format('d M Y H:i') }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No payments found.</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($payments->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $payments->links() }}</div>
  @endif
</div>
@endsection
