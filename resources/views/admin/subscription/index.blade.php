@extends('layouts.admin')

@section('title', 'Subscription')
@section('page-title', 'Subscription')

@section('content')
@php
  $tenant = app('currentTenant');
  $sub    = $tenant?->currentSubscription();
@endphp

<div class="max-w-2xl space-y-5">

  {{-- Current status card --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-4">Current Subscription Status</h3>

    @if($sub)
      @php
        $statusBadge = match($sub->status) {
          'trial'   => ['bg-blue-100 text-blue-700',   'Trial'],
          'active'  => ['bg-emerald-100 text-emerald-700', 'Active'],
          'grace'   => ['bg-amber-100 text-amber-700',  'Grace Period'],
          'locked'  => ['bg-red-100 text-red-700',     'Locked'],
          default   => ['bg-gray-100 text-gray-600',   ucfirst($sub->status)],
        };
      @endphp

      <div class="flex items-center gap-3 mb-4">
        <span class="inline-flex px-3 py-1.5 rounded-full text-sm font-bold {{ $statusBadge[0] }}">
          {{ $statusBadge[1] }}
        </span>
        @if($sub->is_trial)
          <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Trial period</span>
        @endif
      </div>

      <div class="grid grid-cols-2 gap-4 text-sm">
        <div class="bg-gray-50 rounded-lg p-3">
          <p class="text-xs text-gray-400 mb-1">Subscription Term</p>
          <p class="font-semibold text-gray-800">{{ $sub->term?->term_name ?? '—' }}</p>
          <p class="text-xs text-gray-500">{{ $sub->term?->academicYear?->year_label }}</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
          <p class="text-xs text-gray-400 mb-1">Term Ends</p>
          <p class="font-semibold text-gray-800">{{ $sub->term?->end_date?->format('d M Y') ?? '—' }}</p>
        </div>
        @if($sub->status === 'grace')
          <div class="bg-amber-50 rounded-lg p-3">
            <p class="text-xs text-amber-500 mb-1">Grace Ends</p>
            <p class="font-semibold text-amber-800">{{ $sub->grace_ends_at?->format('d M Y') ?? '—' }}</p>
            <p class="text-xs text-amber-600">{{ $sub->graceDaysRemaining() }} day(s) remaining</p>
          </div>
        @endif
      </div>
    @else
      <p class="text-sm text-gray-400">No subscription found. Contact your administrator.</p>
    @endif
  </div>

  {{-- Renew / upgrade --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-2">Renew Subscription</h3>
    <p class="text-sm text-gray-500 mb-4">
      Renew for the next academic term to maintain full access to all features.
    </p>

    <div class="grid grid-cols-3 gap-3 mb-5">
      @foreach(config('billing.plans', []) as $key => $plan)
        <div class="border-2 {{ $key === 'standard' ? 'border-blue-500' : 'border-gray-200' }} rounded-xl p-4 text-center relative">
          @if($key === 'standard')
            <span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-blue-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">Popular</span>
          @endif
          <p class="text-sm font-semibold text-gray-900 capitalize">{{ $key }}</p>
          <p class="text-2xl font-bold text-blue-700 mt-1">GHS {{ number_format($plan['amount'] ?? 0, 0) }}</p>
          <p class="text-xs text-gray-400">per term</p>
        </div>
      @endforeach
    </div>

    <a href="{{ route('payment.page', ['slug' => $tenant?->slug]) }}"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-xl transition-colors text-sm">
      Proceed to Payment →
    </a>
  </div>

  {{-- Payment history --}}
  @php
    $payments = $tenant?->id
      ? \App\Models\Payment::where('tenant_id', $tenant->id)->orderByDesc('created_at')->limit(10)->get()
      : collect();
  @endphp
  @if($payments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-base font-semibold text-gray-900">Payment History</h3>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Date</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Reference</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($payments as $payment)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $payment->created_at->format('d M Y') }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ $payment->reference }}</td>
              <td class="px-4 py-2.5 text-right font-semibold text-gray-800">GHS {{ number_format($payment->amount, 2) }}</td>
              <td class="px-4 py-2.5 text-center">
                @php $pb = match($payment->status) { 'success' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', default => 'bg-red-100 text-red-600' }; @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pb }}">{{ ucfirst($payment->status) }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

</div>
@endsection
