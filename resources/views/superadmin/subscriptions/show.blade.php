@extends('layouts.superadmin')

@section('title', 'Subscription Detail')
@section('page-title', 'Subscription Detail')

@section('content')

@php
  $statusColour = match($subscription->status) {
    'active'    => 'bg-emerald-100 text-emerald-700',
    'trial'     => 'bg-blue-100 text-blue-700',
    'grace'     => 'bg-amber-100 text-amber-700',
    'locked'    => 'bg-red-100 text-red-600',
    'suspended' => 'bg-gray-200 text-gray-600',
    default     => 'bg-gray-100 text-gray-500',
  };
@endphp

{{-- Header --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
  <div>
    <p class="text-xs text-gray-400 mb-0.5">{{ $subscription->tenant?->name }}</p>
    <h2 class="text-xl font-bold text-gray-900">
      {{ $subscription->term?->term_name }} · {{ $subscription->term?->academicYear?->year_label }}
    </h2>
    <span class="inline-flex mt-1 px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $statusColour }}">
      {{ ucfirst($subscription->status) }}
      @if($subscription->is_trial) · Trial @endif
    </span>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('superadmin.subscriptions.edit', $subscription) }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      Edit
    </a>
    <a href="{{ route('superadmin.subscriptions') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
      ← Subscriptions
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  {{-- Left: details + quick actions --}}
  <div class="space-y-4">

    {{-- Details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Details</h3>
      @foreach([
        ['School',       $subscription->tenant?->name],
        ['Plan',         $subscription->plan_id ? ucfirst($subscription->plan_id) : '—'],
        ['Amount',       'GHS ' . number_format($subscription->amount, 2)],
        ['Start Date',   $subscription->start_date?->format('d M Y') ?? '—'],
        ['End Date',     $subscription->end_date?->format('d M Y') ?? '—'],
        ['Grace Ends',   $subscription->grace_ends_at?->format('d M Y H:i') ?? '—'],
        ['Activated At', $subscription->activated_at?->format('d M Y H:i') ?? '—'],
        ['Locked At',    $subscription->locked_at?->format('d M Y H:i') ?? '—'],
        ['Created',      $subscription->created_at->format('d M Y')],
      ] as [$label, $val])
        <div class="flex justify-between py-1.5 border-b border-gray-50 text-sm last:border-0">
          <span class="text-gray-400">{{ $label }}</span>
          <span class="text-gray-800 font-medium text-right">{{ $val }}</span>
        </div>
      @endforeach
    </div>

    {{-- Manual Transition --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Manual Transition</h3>
      <div class="space-y-2">
        @foreach([
          ['activate', 'Activate',   'bg-emerald-600 hover:bg-emerald-700'],
          ['grace',    'Set to Grace','bg-amber-500  hover:bg-amber-600'],
          ['lock',     'Lock',        'bg-red-600    hover:bg-red-700'],
          ['suspend',  'Suspend',     'bg-gray-600   hover:bg-gray-700'],
        ] as [$action, $label, $btnClass])
          <form method="POST" action="{{ route('superadmin.subscriptions.transition', $subscription) }}">
            @csrf
            <input type="hidden" name="action" value="{{ $action }}">
            <button type="submit"
                    class="{{ $btnClass }} text-white text-sm font-medium w-full py-2 rounded-lg transition-colors
                           {{ $subscription->status === $action || ($action === 'activate' && $subscription->status === 'active') ? 'opacity-40 cursor-not-allowed' : '' }}"
                    {{ ($subscription->status === $action || ($action === 'activate' && $subscription->status === 'active')) ? 'disabled' : '' }}>
              {{ $label }}
            </button>
          </form>
        @endforeach
      </div>
    </div>

    {{-- Extend Grace --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Extend Grace Period</h3>
      <form method="POST" action="{{ route('superadmin.subscriptions.extend-grace', $subscription) }}" class="flex gap-2">
        @csrf
        <input type="number" name="days" value="7" min="1" max="90"
               class="w-20 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <button type="submit"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 rounded-lg transition-colors">
          Add Days
        </button>
      </form>
      @if($subscription->grace_ends_at)
        <p class="text-xs text-gray-400 mt-2">
          Current grace end: {{ $subscription->grace_ends_at->format('d M Y H:i') }}
          ({{ $subscription->graceDaysRemaining() }} day(s) remaining)
        </p>
      @endif
    </div>

  </div>

  {{-- Right: payments --}}
  <div class="lg:col-span-2">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Payments for this Subscription</h3>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Date</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Gateway</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Reference</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($subscription->payments as $pay)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $pay->created_at->format('d M Y') }}</td>
              <td class="px-4 py-2.5 text-gray-600 text-xs capitalize">{{ $pay->gateway }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-gray-400">{{ Str::limit($pay->reference, 24) }}</td>
              <td class="px-4 py-2.5 text-right font-semibold text-gray-800">GHS {{ number_format($pay->amount, 2) }}</td>
              <td class="px-4 py-2.5 text-center">
                @php $pb = match($pay->status) { 'success' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', default => 'bg-red-100 text-red-600' }; @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pb }}">{{ ucfirst($pay->status) }}</span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">No payments recorded for this subscription.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
