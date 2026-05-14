@extends('errors.layout')

@section('title', 'Server Error')
@section('code',  '500')
@section('heading', 'Something went wrong')
@section('message', 'We\'re sorry — an unexpected error occurred on our end. Our team has been notified. Please try again in a moment.')

@section('actions')
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="javascript:location.reload()"
           class="inline-flex items-center gap-2 rounded-lg bg-white border border-gray-300 px-5 py-2.5
                  text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0
                         a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Try again
        </a>
        <a href="/"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5
                  text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3
                         m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Home
        </a>
    </div>
@endsection
