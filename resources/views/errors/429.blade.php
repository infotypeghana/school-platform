@extends('errors.layout')

@section('title', 'Too Many Requests')
@section('code',  '429')
@section('heading', 'Too many attempts')
@section('message', 'You\'ve made too many requests in a short period. For your security, please wait a minute before trying again.')

@section('actions')
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="javascript:history.back()"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5
                  text-sm font-medium text-white hover:bg-indigo-700 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Go back
        </a>
    </div>
@endsection
