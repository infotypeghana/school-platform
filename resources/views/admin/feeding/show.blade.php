@extends('layouts.admin')

@section('title', 'Feeding Fee — ' . $fee->student?->full_name)
@section('page-title', 'Feeding Fees')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.feeding.index', ['term_id' => $fee->term_id, 'class_id' => $fee->school_class_id]) }}"
     class="text-sm text-blue-600 hover:underline">← Back to list</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  {{-- ── Left: Fee Detail ─────────────────────────────────────────────── --}}
  <div class="lg:col-span-2 space-y-5">

    {{-- Header card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <div class="flex items-start justify-between gap-4 mb-4">
        <div>
          <h2 class="text-lg font-bold text-gray-900">{{ $fee->student?->full_name }}</h2>
          <p class="text-sm text-gray-500">{{ $fee->student?->admission_number }} · {{ $fee->schoolClass?->name }}</p>
          <p class="text-xs text-gray-400 mt-0.5">{{ $fee->term?->term_name }} {{ $fee->term?->academicYear?->year_label }}</p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $fee->statusBadgeClass() }}">
          {{ ucfirst($fee->status) }}
        </span>
      </div>

      <div class="grid grid-cols-3 gap-4 text-center">
        <div class="bg-gray-50 rounded-lg p-3">
          <div class="text-xl font-bold text-gray-900">GHS {{ number_format($fee->amount_due, 2) }}</div>
          <div class="text-xs text-gray-500 mt-0.5">Total Due</div>
        </div>
        <div class="bg-green-50 rounded-lg p-3">
          <div class="text-xl font-bold text-green-700">GHS {{ number_format($fee->amount_paid, 2) }}</div>
          <div class="text-xs text-gray-500 mt-0.5">Paid</div>
        </div>
        <div class="bg-red-50 rounded-lg p-3">
          <div class="text-xl font-bold text-red-700">GHS {{ number_format($fee->balance(), 2) }}</div>
          <div class="text-xs text-gray-500 mt-0.5">Balance</div>
        </div>
      </div>

      {{-- Rate breakdown --}}
      <div class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-500 space-y-1">
        <div class="flex justify-between">
          <span>Rate per Day</span>
          <span class="font-medium text-gray-700">GHS {{ number_format($fee->rate_per_day, 2) }}</span>
        </div>
        <div class="flex justify-between">
          <span>Feeding Days</span>
          <span class="font-medium text-gray-700">{{ $fee->feeding_days }} days</span>
        </div>
        <div class="flex justify-between">
          <span>Billing Mode</span>
          <span class="font-medium text-gray-700">{{ ucfirst($fee->billing_mode) }}</span>
        </div>
        @if($fee->is_exempt)
          <div class="flex justify-between text-purple-600">
            <span>Exemption Reason</span>
            <span class="font-medium">{{ $fee->exemption_reason ?? 'Not specified' }}</span>
          </div>
        @endif
        @if($fee->notes)
          <div class="flex justify-between">
            <span>Notes</span>
            <span class="font-medium text-gray-700 max-w-xs text-right">{{ $fee->notes }}</span>
          </div>
        @endif
      </div>
    </div>

    {{-- Payment history --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-900">Payment History</h3>
      </div>
      @if($fee->payments->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No payments recorded yet.</p>
      @else
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Receipt</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
              <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
              <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">By</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach($fee->payments as $payment)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5 text-xs font-mono text-gray-600">{{ $payment->receipt_number }}</td>
                <td class="px-4 py-2.5 text-sm text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                <td class="px-4 py-2.5 text-sm text-gray-600">{{ $payment->methodLabel() }}</td>
                <td class="px-4 py-2.5 text-sm text-right font-semibold text-green-700">GHS {{ number_format($payment->amount, 2) }}</td>
                <td class="px-4 py-2.5 text-xs text-gray-400">{{ $payment->recordedBy?->name ?? 'System' }}</td>
                <td class="px-4 py-2.5 text-right">
                  <a href="{{ route('admin.feeding.receipt', $payment->id) }}" target="_blank"
                     class="text-blue-600 hover:text-blue-700 text-xs">Receipt</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

  </div>

  {{-- ── Right: Actions ───────────────────────────────────────────────── --}}
  <div class="space-y-4">

    {{-- Record payment --}}
    @if(!$fee->is_exempt && $fee->balance() > 0)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-900 mb-3">Record Payment</h3>

      @if($errors->any())
        <div class="mb-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-3 py-2 text-xs">
          @foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
      @endif

      <form method="POST" action="{{ route('admin.feeding.pay', $fee->id) }}" class="space-y-3">
        @csrf
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Amount (GHS) *</label>
          <input type="number" name="amount" step="0.01" min="0.01"
                 max="{{ $fee->balance() }}"
                 value="{{ old('amount', number_format($fee->balance(), 2, '.', '')) }}"
                 required
                 class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
          <p class="text-xs text-gray-400 mt-0.5">Balance: GHS {{ number_format($fee->balance(), 2) }}</p>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date *</label>
          <input type="date" name="payment_date" required
                 value="{{ old('payment_date', today()->toDateString()) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Method *</label>
          <select name="payment_method" required
                  class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="cash" selected>Cash</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
          <input type="text" name="notes" value="{{ old('notes') }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500"
                 placeholder="Optional">
        </div>
        <button type="submit"
                class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-lg text-sm transition-colors">
          Record Payment
        </button>
      </form>
    </div>
    @endif

    {{-- Exemption --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-900 mb-1">Exemption</h3>
      <p class="text-xs text-gray-500 mb-3">
        {{ $fee->is_exempt ? 'This student is currently exempt from feeding fees.' : 'Mark this student as exempt (e.g. scholarship, bursary).' }}
      </p>
      <form method="POST" action="{{ route('admin.feeding.exempt', $fee->id) }}" class="space-y-2">
        @csrf
        @if(!$fee->is_exempt)
          <input type="text" name="exemption_reason" placeholder="Reason (optional)"
                 class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
        @endif
        <button type="submit"
                class="w-full {{ $fee->is_exempt ? 'bg-gray-100 hover:bg-gray-200 text-gray-700' : 'bg-purple-600 hover:bg-purple-700 text-white' }} font-semibold py-2 rounded-lg text-sm transition-colors">
          {{ $fee->is_exempt ? 'Remove Exemption' : 'Mark as Exempt' }}
        </button>
      </form>
    </div>

    {{-- Student link --}}
    <a href="{{ route('admin.students.show', $fee->student_id) }}"
       class="block text-center text-sm text-blue-600 hover:underline">
      View Full Student Profile →
    </a>

  </div>
</div>

@endsection
