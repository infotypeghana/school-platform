@extends('errors.layout')

@section('title', 'Access Denied')
@section('code',  '403')
@section('heading', 'Access denied')
@section('message', 'You don\'t have permission to view this page. If you think this is a mistake, contact your administrator.')

@section('actions')
    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
        <a href="javascript:history.back()"
           class="inline-flex items-center gap-2 rounded-lg bg-white border border-gray-300 px-5 py-2.5
                  text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Go back
        </a>
    </div>
@endsection
