@use('Illuminate\Support\Facades\Storage')
@extends('layouts.admin')

@section('title', $student->full_name)
@section('page-title', 'Student Profile')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div class="flex items-center gap-4">
    {{-- Photo or initials avatar --}}
    @if($student->photo)
      <img src="{{ Storage::url($student->photo) }}" alt="{{ $student->full_name }}"
           class="h-14 w-14 rounded-xl object-cover border border-gray-200 flex-shrink-0">
    @else
      <div class="h-14 w-14 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
        <span class="text-lg font-bold text-blue-700">
          {{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}
        </span>
      </div>
    @endif
    <div>
      <h2 class="text-xl font-bold text-gray-900">{{ $student->full_name }}</h2>
      <p class="text-sm text-gray-500">{{ $student->admission_number ?? 'No admission no.' }} · {{ $student->schoolClass?->full_name ?? '—' }}</p>
    </div>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('admin.students.transcript', $student) }}" target="_blank"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-purple-300 text-purple-700 text-sm font-medium hover:bg-purple-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      Transcript
    </a>
    <a href="{{ route('admin.students.edit', $student) }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      Edit Profile
    </a>
    <a href="{{ route('admin.students') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
      Back
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  {{-- Left: Details --}}
  <div class="lg:col-span-1 space-y-5">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Personal Details</h3>
      @foreach([
          ['Date of Birth', $student->date_of_birth?->format('d M Y') ?? '—'],
          ['Gender',        ucfirst($student->gender ?? '—')],
          ['Address',       $student->address ?? '—'],
      ] as [$label, $val])
        <div class="flex justify-between py-1.5 border-b border-gray-50 text-sm">
          <span class="text-gray-500">{{ $label }}</span>
          <span class="text-gray-900 font-medium">{{ $val }}</span>
        </div>
      @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">Guardian</h3>
      @foreach([
          ['Name',  $student->guardian_name  ?? '—'],
          ['Phone', $student->guardian_phone ?? '—'],
          ['Email', $student->guardian_email ?? '—'],
      ] as [$label, $val])
        <div class="flex justify-between py-1.5 border-b border-gray-50 text-sm">
          <span class="text-gray-500">{{ $label }}</span>
          <span class="text-gray-900 font-medium truncate max-w-[160px]">{{ $val }}</span>
        </div>
      @endforeach
    </div>

    {{-- Status badge --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-2">Enrolment</h3>
      <p class="text-xs text-gray-500 mb-1">Admission Date: {{ $student->admission_date?->format('d M Y') ?? '—' }}</p>
      @php
        $badge = match($student->status) {
          'active'    => 'bg-emerald-100 text-emerald-700',
          'inactive'  => 'bg-gray-100 text-gray-600',
          'graduated' => 'bg-blue-100 text-blue-700',
          'withdrawn' => 'bg-red-100 text-red-600',
          default     => 'bg-gray-100 text-gray-600',
        };
      @endphp
      <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold {{ $badge }}">
        {{ ucfirst($student->status) }}
      </span>
    </div>
  </div>

  {{-- Right: Academic summary --}}
  <div class="lg:col-span-2 space-y-5">

    {{-- Assessments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Recent Assessments</h3>
        <span class="text-xs text-gray-400">{{ $student->assessments->count() }} records</span>
      </div>
      @if($student->assessments->isEmpty())
        <p class="px-5 py-4 text-sm text-gray-400">No assessments recorded yet.</p>
      @else
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Subject</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">CA</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Exam</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Total</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Grade</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @foreach($student->assessments->take(10) as $a)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-700 font-medium">{{ $a->subject?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ number_format($a->ca_score, 1) }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ number_format($a->exam_score, 1) }}</td>
                <td class="px-4 py-2 text-center font-semibold text-gray-900">{{ number_format($a->total_score, 1) }}</td>
                <td class="px-4 py-2 text-center font-bold
                  {{ $a->grade === 'A1' ? 'text-emerald-600' : ($a->grade === 'F9' ? 'text-red-500' : 'text-blue-600') }}">
                  {{ $a->grade }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    {{-- Attendance Summary --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-gray-700">Attendance</h3>
        @if($attendanceSummary['rate'] !== null)
          @php
            $rate  = $attendanceSummary['rate'];
            $rateColour = $rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-500');
          @endphp
          <span class="text-lg font-bold {{ $rateColour }}">{{ $rate }}%</span>
        @endif
      </div>
      @if($attendanceSummary['total'] === 0)
        <p class="text-sm text-gray-400">No attendance records yet.</p>
      @else
        <div class="grid grid-cols-4 gap-3 text-center">
          @foreach([
            ['Present', $attendanceSummary['present'], 'bg-emerald-50 text-emerald-700'],
            ['Absent',  $attendanceSummary['absent'],  'bg-red-50    text-red-600'],
            ['Late',    $attendanceSummary['late'],    'bg-amber-50  text-amber-700'],
            ['Excused', $attendanceSummary['excused'], 'bg-blue-50   text-blue-700'],
          ] as [$label, $count, $colour])
            <div class="rounded-lg p-3 {{ $colour }}">
              <div class="text-xl font-bold">{{ $count }}</div>
              <div class="text-xs mt-0.5">{{ $label }}</div>
            </div>
          @endforeach
        </div>
        <p class="text-xs text-gray-400 mt-3 text-right">
          {{ $attendanceSummary['total'] }} session(s) recorded
        </p>
      @endif
    </div>

    {{-- Fees --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700">Fee Records</h3>
        <span class="text-xs text-gray-400">{{ $student->fees->count() }} records</span>
      </div>
      @if($student->fees->isEmpty())
        <p class="px-5 py-4 text-sm text-gray-400">No fee records.</p>
      @else
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Description</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount Due</th>
              <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Paid</th>
              <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @foreach($student->fees as $fee)
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-700">{{ $fee->fee_type }}</td>
                <td class="px-4 py-2 text-right text-gray-600">GHS {{ number_format($fee->amount, 2) }}</td>
                <td class="px-4 py-2 text-right text-gray-600">GHS {{ number_format($fee->amount_paid, 2) }}</td>
                <td class="px-4 py-2 text-center">
                  @php
                    $fb = match($fee->status) {
                      'paid'    => 'bg-emerald-100 text-emerald-700',
                      'partial' => 'bg-amber-100 text-amber-700',
                      default   => 'bg-red-100 text-red-600',
                    };
                  @endphp
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $fb }}">
                    {{ ucfirst($fee->status) }}
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>
  </div>
</div>
@endsection
