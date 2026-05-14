@extends('layouts.teacher-portal')
@section('title', 'My Timetable')

@section('content')

<div class="mb-5">
    <h1 class="text-xl font-bold text-gray-900">My Timetable</h1>
    <p class="text-sm text-gray-500 mt-0.5">Weekly schedule for your classes and subjects.</p>
</div>

@if($timetable->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
        </div>
        <p class="text-gray-600 font-medium">No timetable entries yet</p>
        <p class="text-sm text-gray-400 mt-1">Your administrator has not set up the timetable yet.</p>
    </div>
@else
    <div class="space-y-4">
        {{-- $days is [1=>'Monday', 2=>'Tuesday', ...] --}}
        @foreach($days as $dayNum => $dayName)
            @if(isset($timetable[$dayNum]))
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">{{ $dayName }}</h2>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($timetable[$dayNum] as $entry)
                            <div class="px-5 py-3 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 text-xs text-gray-500 font-medium flex-shrink-0">
                                        {{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }}<br>
                                        <span class="text-gray-400">{{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}</span>
                                    </div>
                                    <div class="w-1 h-8 rounded-full bg-blue-400 flex-shrink-0"></div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            {{ $entry->subject?->name ?? $entry->label ?? 'Period' }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ $entry->schoolClass?->full_name ?? '—' }}
                                            @if($entry->period_number)
                                                · Period {{ $entry->period_number }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                @php
                                    $duration = \Carbon\Carbon::parse($entry->start_time)
                                        ->diffInMinutes(\Carbon\Carbon::parse($entry->end_time));
                                @endphp
                                <span class="text-xs text-gray-400 flex-shrink-0">{{ $duration }} min</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
@endif

@endsection
