@use('Illuminate\Support\Facades\Storage')
@extends('layouts.website')

@section('title', $student->full_name . ' — Parent Portal')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">

  {{-- Header bar --}}
  <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
    <a href="{{ route('website.home') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      School Website
    </a>
    <form method="POST" action="{{ route('website.portal.logout') }}" class="inline">
      @csrf
      <button type="submit"
              class="inline-flex items-center gap-1.5 text-sm text-red-500 hover:text-red-700 transition-colors font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        Log out
      </button>
    </form>
  </div>

  {{-- ── Ward switcher (shown only when 2+ wards are linked) ── --}}
  @if($allWards->count() > 1)
  <div class="bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 mb-5">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div>
        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">Viewing records for</p>
        <div class="flex flex-wrap gap-2">
          @foreach($allWards as $ward)
            @if($ward->id === $student->id)
              {{-- Active ward --}}
              <span class="inline-flex items-center gap-1.5 bg-blue-600 text-white text-sm font-semibold px-3 py-1.5 rounded-full">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                {{ $ward->first_name }} {{ $ward->last_name }}
                <span class="text-blue-200 font-normal text-xs">({{ $ward->schoolClass?->full_name ?? '—' }})</span>
              </span>
            @else
              {{-- Inactive ward — click to switch --}}
              <form method="POST" action="{{ route('website.portal.switch-ward') }}" class="inline">
                @csrf
                <input type="hidden" name="student_id" value="{{ $ward->id }}">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 bg-white border border-blue-300 text-blue-700 hover:bg-blue-600 hover:text-white hover:border-blue-600 text-sm font-medium px-3 py-1.5 rounded-full transition-colors">
                  {{ $ward->first_name }} {{ $ward->last_name }}
                  <span class="text-gray-400 text-xs">({{ $ward->schoolClass?->full_name ?? '—' }})</span>
                </button>
              </form>
            @endif
          @endforeach
        </div>
      </div>
      <a href="{{ route('website.portal.add-ward') }}"
         class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-800 font-medium border border-blue-300 hover:border-blue-500 px-3 py-1.5 rounded-full transition-colors whitespace-nowrap">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add another child
      </a>
    </div>
  </div>
  @else
  {{-- Single ward — show a subtle "add another child" link --}}
  <div class="flex justify-end mb-3">
    <a href="{{ route('website.portal.add-ward') }}"
       class="inline-flex items-center gap-1.5 text-xs text-gray-400 hover:text-blue-600 transition-colors">
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Add another child
    </a>
  </div>
  @endif

  {{-- Flash messages --}}
  @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3">
      {{ session('success') }}
    </div>
  @endif

  {{-- Student header card --}}
  <div class="bg-white rounded-2xl border border-gray-200 shadow-sm px-6 py-5 mb-6 flex items-center gap-5">
    @if($student->photo)
      <img src="{{ Storage::url($student->photo) }}" alt="{{ $student->full_name }}"
           class="h-16 w-16 rounded-xl object-cover border border-gray-200 flex-shrink-0">
    @else
      <div class="h-16 w-16 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
        <span class="text-xl font-bold text-blue-700">
          {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
        </span>
      </div>
    @endif
    <div class="flex-1 min-w-0">
      <h1 class="text-xl font-bold text-gray-900">{{ $student->full_name }}</h1>
      <p class="text-sm text-gray-500 mt-0.5">
        {{ $student->admission_number }} · {{ $student->schoolClass?->full_name ?? '—' }}
      </p>
      @php
        $badge = match($student->status) {
          'active'    => 'bg-emerald-100 text-emerald-700',
          'graduated' => 'bg-blue-100 text-blue-700',
          default     => 'bg-gray-100 text-gray-600',
        };
      @endphp
      <span class="inline-flex mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
        {{ ucfirst($student->status) }}
      </span>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left col --}}
    <div class="space-y-6">

      {{-- Attendance --}}
      @if($attendanceSummary)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-3">Attendance Summary</h2>
          @php $rate = $attendanceSummary['rate']; $rc = $rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-500'); @endphp
          <div class="text-center mb-3">
            <span class="text-3xl font-bold {{ $rc }}">{{ $rate }}%</span>
            <p class="text-xs text-gray-400">Attendance rate</p>
          </div>
          <div class="grid grid-cols-3 gap-2 text-center text-xs">
            <div class="bg-emerald-50 rounded-lg p-2">
              <div class="font-bold text-emerald-700">{{ $attendanceSummary['present'] }}</div>
              <div class="text-emerald-600">Present</div>
            </div>
            <div class="bg-red-50 rounded-lg p-2">
              <div class="font-bold text-red-600">{{ $attendanceSummary['absent'] }}</div>
              <div class="text-red-500">Absent</div>
            </div>
            <div class="bg-amber-50 rounded-lg p-2">
              <div class="font-bold text-amber-600">{{ $attendanceSummary['late'] }}</div>
              <div class="text-amber-500">Late</div>
            </div>
          </div>
          <p class="text-xs text-gray-400 mt-2 text-center">{{ $attendanceSummary['total'] }} sessions recorded</p>
        </div>
      @endif

      {{-- Current term fees --}}
      @if($currentTerm)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
          <h2 class="text-sm font-semibold text-gray-900 mb-1">Current Term Fees</h2>
          <p class="text-xs text-gray-400 mb-3">{{ $currentTerm->term_name }}</p>
          @if($currentFees->isEmpty())
            <p class="text-sm text-gray-400">No fee records for this term.</p>
          @else
            <div class="space-y-2">
              @foreach($currentFees as $fee)
                @php
                  $sb = match($fee->status) { 'paid' => 'bg-emerald-100 text-emerald-700', 'partial' => 'bg-amber-100 text-amber-700', default => 'bg-red-100 text-red-600' };
                @endphp
                <div class="border border-gray-100 rounded-lg p-3">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-800">{{ $fee->fee_type }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $sb }}">{{ ucfirst($fee->status) }}</span>
                  </div>
                  <div class="flex justify-between text-xs text-gray-500">
                    <span>Due: GHS {{ number_format($fee->amount, 2) }}</span>
                    <span>Paid: GHS {{ number_format($fee->amount_paid, 2) }}</span>
                  </div>
                  @if($fee->balance > 0)
                    <p class="text-xs text-red-600 mt-1 font-medium">Balance: GHS {{ number_format($fee->balance, 2) }}</p>
                  @endif
                </div>
              @endforeach
            </div>
          @endif
        </div>
      @endif

    </div>

    {{-- Right col — assessments --}}
    <div class="lg:col-span-2 space-y-6">

      @forelse($assessmentsByTerm as $termId => $assessments)
        @php $term = $assessments->first()->term; @endphp
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-900">
              {{ $term->term_name ?? 'Term' }}
              @if($term->academicYear) <span class="text-gray-400 font-normal">· {{ $term->academicYear->year_label }}</span> @endif
            </h2>
          </div>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Subject</th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">CA<span class="font-normal text-gray-400">/{{ $caMax }}</span></th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Exam<span class="font-normal text-gray-400">/{{ $examMax }}</span></th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Total</th>
                <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Grade</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              @foreach($assessments as $a)
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-2.5 text-gray-800">{{ $a->subject?->name ?? '—' }}</td>
                  <td class="px-4 py-2.5 text-center text-gray-600">{{ number_format($a->ca_score, 1) }}</td>
                  <td class="px-4 py-2.5 text-center text-gray-600">{{ number_format($a->exam_score, 1) }}</td>
                  <td class="px-4 py-2.5 text-center font-semibold text-gray-900">{{ number_format($a->total_score, 1) }}</td>
                  <td class="px-4 py-2.5 text-center">
                    @php
                      $gc = str_starts_with($a->grade ?? '', 'A') ? 'bg-emerald-100 text-emerald-700'
                          : (str_starts_with($a->grade ?? '', 'B') ? 'bg-blue-100 text-blue-700'
                          : (str_starts_with($a->grade ?? '', 'C') ? 'bg-amber-100 text-amber-700'
                          : 'bg-red-100 text-red-600'));
                    @endphp
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-bold {{ $gc }}">
                      {{ $a->grade ?? '—' }}
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @empty
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-10 text-center text-gray-400 text-sm">
          No assessment records available yet.
        </div>
      @endforelse

    </div>
  </div>

  <p class="text-center text-xs text-gray-400 mt-8">
    For queries about records, please contact the school office directly.
  </p>
</div>
@endsection
