@extends('layouts.admin')

@section('title', 'Attendance Report')
@section('page-title', 'Attendance Report')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $class->full_name }}</h2>
    <p class="text-sm text-gray-500">
      {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} —
      {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
    </p>
  </div>
  <a href="{{ route('admin.attendance') }}" class="text-sm text-blue-600 hover:underline">← New report</a>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-900 text-white">
      <tr>
        <th class="px-4 py-3 text-left font-semibold">#</th>
        <th class="px-4 py-3 text-left font-semibold">Student</th>
        <th class="px-4 py-3 text-center font-semibold text-emerald-300">Present</th>
        <th class="px-4 py-3 text-center font-semibold text-red-300">Absent</th>
        <th class="px-4 py-3 text-center font-semibold text-amber-300">Late</th>
        <th class="px-4 py-3 text-center font-semibold text-blue-300">Excused</th>
        <th class="px-4 py-3 text-center font-semibold">Total Days</th>
        <th class="px-4 py-3 text-center font-semibold">Attendance %</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($summary as $i => $row)
        @php
          $rate = $row['total'] > 0 ? round(($row['present'] / $row['total']) * 100) : 0;
          $rateColor = $rate >= 80 ? 'text-emerald-600' : ($rate >= 60 ? 'text-amber-600' : 'text-red-600');
        @endphp
        <tr class="hover:bg-gray-50 transition-colors {{ $loop->even ? 'bg-gray-50/30' : '' }}">
          <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $loop->iteration }}</td>
          <td class="px-4 py-2.5 font-medium text-gray-900">{{ $row['student']->full_name }}</td>
          <td class="px-4 py-2.5 text-center font-semibold text-emerald-700">{{ $row['present'] }}</td>
          <td class="px-4 py-2.5 text-center font-semibold text-red-500">{{ $row['absent'] }}</td>
          <td class="px-4 py-2.5 text-center text-amber-600">{{ $row['late'] }}</td>
          <td class="px-4 py-2.5 text-center text-blue-500">{{ $row['excused'] }}</td>
          <td class="px-4 py-2.5 text-center text-gray-600">{{ $row['total'] }}</td>
          <td class="px-4 py-2.5 text-center font-bold {{ $rateColor }}">{{ $rate }}%</td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">No attendance records found for this period.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
@endsection
