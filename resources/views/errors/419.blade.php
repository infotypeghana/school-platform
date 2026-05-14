@extends('errors.layout')

@section('title', 'Session Expired')
@section('code',  '419')
@section('heading', 'Session expired')
@section('message', 'Your session has expired for security reasons. Please refresh the page and try again.')

@section('actions')
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="javascript:location.reload()"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5
                  text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0
                         a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh page
        </a>
    </div>
@endsection
