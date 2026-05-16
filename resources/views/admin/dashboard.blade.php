@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')

{{-- ── Onboarding checklist ─────────────────────────────────────────────────── --}}
@if($onboarding)
<div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-5">
  <div class="flex items-center gap-3 mb-4">
    <div class="h-8 w-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
      <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
    </div>
    <div>
      <p class="font-semibold text-blue-900 text-sm">Getting Started Checklist</p>
      <p class="text-blue-600 text-xs">Complete these steps to set up your school</p>
    </div>
  </div>
  <div class="space-y-2">
    @foreach($onboarding as $step)
      <div class="flex items-center gap-3">
        @if($step['done'])
          <div class="w-5 h-5 bg-emerald-500 rounded-full flex items-center justify-center flex-shrink-0">
            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
            </svg>
          </div>
          <span class="text-sm text-gray-500 line-through">{{ $step['label'] }}</span>
        @else
          <div class="w-5 h-5 border-2 border-blue-300 rounded-full flex-shrink-0"></div>
          <a href="{{ route($step['route']) }}" class="text-sm text-blue-700 font-medium hover:underline">
            {{ $step['label'] }} →
          </a>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- ── Stats row ──────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

    {{-- Students --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Total Students</p>
            <div class="h-9 w-9 bg-blue-50 rounded-lg flex items-center justify-center">
                <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900">{{ number_format($totalStudents) }}</p>
        <p class="text-xs text-gray-400 mt-1">Active this term</p>
    </div>

    {{-- Teachers --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Teachers</p>
            <div class="h-9 w-9 bg-emerald-50 rounded-lg flex items-center justify-center">
                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900">{{ number_format($totalTeachers) }}</p>
        <p class="text-xs text-gray-400 mt-1">On staff</p>
    </div>

    {{-- Classes --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Classes</p>
            <div class="h-9 w-9 bg-violet-50 rounded-lg flex items-center justify-center">
                <svg class="h-5 w-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900">{{ number_format($totalClasses) }}</p>
        <p class="text-xs text-gray-400 mt-1">Registered classes</p>
    </div>

    {{-- Fees Collected --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Fees Collected</p>
            <div class="h-9 w-9 bg-amber-50 rounded-lg flex items-center justify-center">
                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-gray-900">GHS {{ number_format($feesCollected, 0) }}</p>
        <p class="text-xs text-gray-400 mt-1">{{ $term ? 'This term' : 'All time' }}</p>
    </div>

</div>

{{-- ── Main content row ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- Current Term Card --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 lg:col-span-2">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Current Academic Term</h2>
        @if($term)
            <div class="flex items-start gap-4">
                <div class="h-12 w-12 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="text-white font-bold text-lg">{{ $term->term_number }}</span>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-900">{{ $term->term_name }}</p>
                    <p class="text-sm text-gray-500">{{ $term->academicYear?->year_label }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <p class="text-gray-400 text-xs">Start Date</p>
                            <p class="font-medium text-gray-700">{{ $term->start_date->format('d M Y') }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <p class="text-gray-400 text-xs">End Date</p>
                            <p class="font-medium text-gray-700">{{ $term->end_date->format('d M Y') }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Term Progress</span>
                            <span>{{ $daysLeft }} days remaining</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all"
                                 style="width: {{ $termProgress }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <p class="text-gray-400 text-sm">No current term configured. Contact your super admin.</p>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div class="space-y-2">
            @foreach([
                ['Add Student',       'admin.students.create', 'text-blue-600   bg-blue-50'],
                ['Record Attendance', 'admin.attendance',      'text-emerald-600 bg-emerald-50'],
                ['Enter Scores',      'admin.assessments.index','text-violet-600 bg-violet-50'],
                ['Collect Fee',       'admin.fees.create',     'text-amber-600  bg-amber-50'],
            ] as [$label, $route, $colors])
                <a href="{{ route($route) }}"
                   class="flex items-center gap-3 p-3 rounded-lg {{ $colors }} hover:opacity-80 transition-opacity">
                    <span class="text-sm font-medium">{{ $label }}</span>
                    <svg class="h-4 w-4 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endforeach

            {{-- Pending admissions badge --}}
            @if($pendingAdmissions > 0)
                <a href="{{ route('admin.admissions.index', ['status' => 'pending']) }}"
                   class="flex items-center gap-3 p-3 rounded-lg text-rose-600 bg-rose-50 hover:opacity-80 transition-opacity">
                    <span class="text-sm font-medium">Admissions</span>
                    <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5
                                 rounded-full text-xs font-bold bg-rose-600 text-white">
                        {{ $pendingAdmissions }}
                    </span>
                </a>
            @else
                <a href="{{ route('admin.admissions.index') }}"
                   class="flex items-center gap-3 p-3 rounded-lg text-rose-600 bg-rose-50 hover:opacity-80 transition-opacity">
                    <span class="text-sm font-medium">Admissions</span>
                    <svg class="h-4 w-4 ml-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            @endif
        </div>
    </div>

</div>
@endsection
