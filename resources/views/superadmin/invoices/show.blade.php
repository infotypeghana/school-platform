@extends('layouts.superadmin')

@section('title', $invoice->invoice_number)
@section('page-title', 'Invoice ' . $invoice->invoice_number)

@section('content')

<div class="flex items-center gap-3 mb-6">
  <a href="{{ route('superadmin.invoices') }}" class="text-gray-400 hover:text-gray-600 text-sm">← Invoices</a>
  <span class="text-gray-300">/</span>
  <span class="text-sm font-medium text-gray-600 font-mono">{{ $invoice->invoice_number }}</span>
</div>

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
@endif

<div class="max-w-2xl space-y-5">

  {{-- Invoice card --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">

    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-1">School</p>
        <p class="font-semibold text-gray-800">{{ $invoice->tenant?->name ?? '—' }}</p>
      </div>
      <span class="inline-flex px-3 py-1 rounded-full text-sm font-medium {{ $invoice->statusBadgeClass() }}">
        {{ $invoice->statusLabel() }}
      </span>
    </div>

    <div class="grid grid-cols-2 gap-4 text-sm border-t border-gray-50 pt-4">
      <div>
        <p class="text-xs text-gray-400 mb-1">Package</p>
        <p class="font-medium text-gray-800">{{ $invoice->package_name }}</p>
        <p class="text-xs text-gray-400">{{ $invoice->billing_cycle === 'annual' ? 'Annual' : 'Per Term' }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Term</p>
        <p class="font-medium text-gray-800">{{ $invoice->term?->term_name ?? '—' }}</p>
        <p class="text-xs text-gray-400">{{ $invoice->term?->academicYear?->year_label }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Students billed</p>
        <p class="font-medium text-gray-800">{{ number_format($invoice->student_count) }}</p>
        <p class="text-xs text-gray-400">GHS {{ number_format($invoice->price_per_student, 2) }} / student</p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-1">Due date</p>
        <p class="font-medium text-gray-800">{{ $invoice->due_date->format('d M Y') }}</p>
        @if($invoice->paid_at)
          <p class="text-xs text-emerald-600">Paid {{ $invoice->paid_at->format('d M Y') }}</p>
        @endif
      </div>
    </div>

    <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
      <p class="text-sm text-gray-500">Total amount</p>
      <p class="text-2xl font-bold text-gray-800">GHS {{ number_format($invoice->amount, 2) }}</p>
    </div>

    @if($invoice->notes)
      <div class="border-t border-gray-100 pt-4">
        <p class="text-xs text-gray-400 mb-1">Notes</p>
        <p class="text-sm text-gray-600">{{ $invoice->notes }}</p>
      </div>
    @endif
  </div>

  {{-- Actions --}}
  @if(! $invoice->isPaid() && ! $invoice->isVoid())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-4">Actions</h3>
      <div class="flex flex-wrap gap-3">
        <form method="POST" action="{{ route('superadmin.invoices.mark-paid', $invoice) }}">
          @csrf
          <button type="submit"
                  class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
            Mark as Paid
          </button>
        </form>
        <form method="POST" action="{{ route('superadmin.invoices.void', $invoice) }}"
              onsubmit="return confirm('Void this invoice? This cannot be undone.')">
          @csrf
          <button type="submit"
                  class="border border-gray-300 text-gray-600 hover:bg-gray-50 text-sm px-5 py-2 rounded-lg transition-colors">
            Void Invoice
          </button>
        </form>
      </div>
    </div>
  @endif

</div>

@endsection
