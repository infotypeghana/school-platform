@extends('layouts.admin')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

  {{-- Take attendance --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-4">Take / Edit Attendance</h3>
    <form method="GET" action="{{ route('admin.attendance.sheet') }}" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
        <select name="class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}">{{ $class->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
        <input type="date" name="date" value="{{ $today }}" max="{{ $today }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">
        Open Attendance Sheet →
      </button>
    </form>
  </div>

  {{-- Attendance report --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="text-base font-semibold text-gray-900 mb-4">View Attendance Report</h3>
    <form method="GET" action="{{ route('admin.attendance.report') }}" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class</label>
        <select name="class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}">{{ $class->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
          <input type="date" name="start_date" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
          <input type="date" name="end_date" value="{{ $today }}" max="{{ $today }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <button type="submit"
              class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 rounded-lg transition-colors text-sm">
        Generate Report →
      </button>
    </form>
  </div>

</div>
@endsection
