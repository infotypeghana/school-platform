<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Feeding Fee Receipt — {{ $payment->receipt_number }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media print {
      .no-print { display: none !important; }
      body { font-size: 13px; }
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-start justify-center py-10 px-4">

{{-- Print button --}}
<div class="no-print fixed top-4 right-4 flex gap-2">
  <button onclick="window.print()"
          class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors">
    🖨 Print Receipt
  </button>
  <button onclick="window.close()"
          class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm transition-colors">
    Close
  </button>
</div>

{{-- Receipt card --}}
<div class="bg-white rounded-2xl shadow-lg max-w-md w-full p-8 border border-gray-200">

  {{-- Header --}}
  <div class="text-center mb-6">
    @if($tenant?->logo)
      <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
           class="h-14 w-14 object-cover rounded-xl mx-auto mb-3 border border-gray-200">
    @else
      <div class="h-14 w-14 bg-blue-600 rounded-xl mx-auto mb-3 flex items-center justify-center">
        <span class="text-white text-xl font-bold">{{ strtoupper(substr($tenant?->name ?? 'S', 0, 1)) }}</span>
      </div>
    @endif
    <h1 class="text-lg font-bold text-gray-900">{{ $tenant?->name }}</h1>
    @if($tenant?->address)
      <p class="text-xs text-gray-400">{{ $tenant->address }}</p>
    @endif
    @if($tenant?->contact_phone)
      <p class="text-xs text-gray-400">Tel: {{ $tenant->contact_phone }}</p>
    @endif
  </div>

  {{-- Receipt title --}}
  <div class="text-center border-t border-dashed border-gray-300 pt-4 mb-4">
    <h2 class="text-sm font-bold tracking-widest uppercase text-gray-600">Feeding Fee Receipt</h2>
  </div>

  {{-- Receipt details --}}
  <div class="space-y-2 text-sm mb-6">
    <div class="flex justify-between">
      <span class="text-gray-500">Receipt No.</span>
      <span class="font-mono font-bold text-gray-900">{{ $payment->receipt_number }}</span>
    </div>
    <div class="flex justify-between">
      <span class="text-gray-500">Date</span>
      <span class="text-gray-900">{{ $payment->payment_date->format('d F Y') }}</span>
    </div>
    <div class="flex justify-between border-t border-gray-100 pt-2 mt-2">
      <span class="text-gray-500">Student</span>
      <span class="font-semibold text-gray-900">{{ $payment->student?->full_name }}</span>
    </div>
    <div class="flex justify-between">
      <span class="text-gray-500">Admission No.</span>
      <span class="text-gray-700">{{ $payment->student?->admission_number }}</span>
    </div>
    <div class="flex justify-between">
      <span class="text-gray-500">Class</span>
      <span class="text-gray-700">{{ $payment->student?->schoolClass?->name }}</span>
    </div>
    <div class="flex justify-between border-t border-gray-100 pt-2 mt-2">
      <span class="text-gray-500">Term</span>
      <span class="text-gray-700">
        {{ $payment->feedingFee?->term?->term_name }}
        {{ $payment->feedingFee?->term?->academicYear?->year_label }}
      </span>
    </div>
    <div class="flex justify-between">
      <span class="text-gray-500">Payment Method</span>
      <span class="text-gray-700">{{ $payment->methodLabel() }}</span>
    </div>
    @if($payment->notes)
    <div class="flex justify-between">
      <span class="text-gray-500">Notes</span>
      <span class="text-gray-700 text-right max-w-xs">{{ $payment->notes }}</span>
    </div>
    @endif
  </div>

  {{-- Amount due/paid/balance breakdown --}}
  @php $fee = $payment->feedingFee; @endphp
  @if($fee)
  <div class="bg-gray-50 rounded-xl p-4 mb-6 text-sm space-y-1.5">
    <div class="flex justify-between text-gray-500">
      <span>Total Due ({{ $fee->feeding_days }} days @ GHS {{ number_format($fee->rate_per_day, 2) }}/day)</span>
      <span>GHS {{ number_format($fee->amount_due, 2) }}</span>
    </div>
    <div class="flex justify-between text-gray-500">
      <span>Previously Paid</span>
      <span>GHS {{ number_format($fee->amount_paid - $payment->amount, 2) }}</span>
    </div>
    <div class="flex justify-between font-bold text-green-700 border-t border-gray-200 pt-1.5">
      <span>This Payment</span>
      <span>GHS {{ number_format($payment->amount, 2) }}</span>
    </div>
    <div class="flex justify-between {{ $fee->balance() > 0 ? 'text-red-600' : 'text-green-600' }} text-sm">
      <span>Remaining Balance</span>
      <span>GHS {{ number_format($fee->balance(), 2) }}</span>
    </div>
  </div>
  @endif

  {{-- Large amount --}}
  <div class="text-center border-t border-dashed border-gray-300 pt-4 mb-6">
    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Amount Received</p>
    <p class="text-3xl font-extrabold text-gray-900">GHS {{ number_format($payment->amount, 2) }}</p>
  </div>

  {{-- Footer --}}
  <div class="border-t border-dashed border-gray-300 pt-4 text-xs text-gray-400 text-center space-y-1">
    <p>Recorded by: {{ $payment->recordedBy?->name ?? 'System' }}</p>
    <p>Printed: {{ now()->format('d M Y, H:i') }}</p>
    <p class="mt-2 font-medium text-gray-500">Thank you — this is your official receipt.</p>
  </div>

</div>

</body>
</html>
