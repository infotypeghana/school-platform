@extends('layouts.website')

@section('title', 'Payment Result')

@section('content')

<div class="max-w-md mx-auto py-12 px-4 text-center">

  @if($status === 'success')
    <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5">
      <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
    @if(isset($payment))
      <p class="text-gray-500 text-sm">
        GHS {{ number_format($payment->amount, 2) }} received for
        {{ $payment->fee?->student?->full_name ?? 'your student' }}.
        A receipt has been sent via SMS.
      </p>
    @endif

  @elseif($status === 'pending')
    <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-5">
      <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Pending</h1>
    <p class="text-gray-500 text-sm">
      Your payment is being processed. Your fee balance will update automatically once confirmed.
    </p>

  @else
    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-5">
      <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h1>
    <p class="text-gray-500 text-sm">Something went wrong. Please try again or contact the school.</p>
  @endif

  <a href="{{ url()->previous() }}"
     class="mt-6 inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-xl transition-colors text-sm">
    Back to Dashboard
  </a>

</div>

@endsection
