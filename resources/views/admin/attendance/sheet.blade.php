@extends('layouts.admin')

@section('title', 'Attendance — ' . $class->full_name)
@section('page-title', 'Attendance Sheet')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $class->full_name }}</h2>
    <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }}</p>
  </div>
  <a href="{{ route('admin.attendance') }}" class="text-sm text-blue-600 hover:underline">← Change class / date</a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

@if($class->students->isEmpty())
  <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-sm">No active students in this class.</div>
@else

{{-- Quick mark buttons --}}
<div class="flex gap-2 mb-4">
  <button type="button" onclick="markAll('present')"
          class="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
    Mark All Present
  </button>
  <button type="button" onclick="markAll('absent')"
          class="bg-red-100 hover:bg-red-200 text-red-600 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
    Mark All Absent
  </button>
  <span class="ml-2 text-xs text-gray-400 self-center">{{ $class->students->count() }} student(s)</span>
</div>

<form method="POST" action="{{ route('admin.attendance.save') }}">
  @csrf
  <input type="hidden" name="class_id" value="{{ $class->id }}">
  <input type="hidden" name="date"     value="{{ $date }}">
  <input type="hidden" name="term_id"  value="{{ $term?->id }}">

  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-5">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-gray-600 w-8">#</th>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Student</th>
          @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $val => $label)
            <th class="px-4 py-3 text-center font-semibold text-gray-600 w-24">{{ $label }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        @foreach($class->students as $i => $student)
          @php $currentStatus = $existing[$student->id]?->status ?? 'present'; @endphp
          <tr class="hover:bg-gray-50 transition-colors {{ $loop->even ? 'bg-gray-50/30' : '' }}">
            <td class="px-4 py-2 text-gray-400 text-xs">{{ $i + 1 }}</td>
            <td class="px-4 py-2">
              <div class="font-medium text-gray-900">{{ $student->full_name }}</div>
              <div class="text-xs text-gray-400">{{ $student->admission_number }}</div>
            </td>
            @foreach(['present' => 'text-emerald-600', 'absent' => 'text-red-500', 'late' => 'text-amber-600', 'excused' => 'text-blue-500'] as $val => $color)
              <td class="px-4 py-2 text-center">
                <label class="cursor-pointer">
                  <input type="radio"
                         name="attendance[{{ $student->id }}]"
                         value="{{ $val }}"
                         class="sr-only"
                         {{ $currentStatus === $val ? 'checked' : '' }}
                         onchange="updateRow(this)">
                  <span class="status-dot inline-flex items-center justify-center w-8 h-8 rounded-full border-2 text-xs font-bold transition-all
                    {{ $currentStatus === $val ? 'border-current ' . $color . ' bg-current/10' : 'border-gray-200 text-gray-300' }}">
                    {{ strtoupper(substr($val, 0, 1)) }}
                  </span>
                </label>
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
    Save Attendance
  </button>
</form>

@endif

<script>
const statusColors = {
  present: 'text-emerald-600',
  absent:  'text-red-500',
  late:    'text-amber-600',
  excused: 'text-blue-500',
};

function updateRow(radio) {
  const row   = radio.closest('tr');
  const dots  = row.querySelectorAll('.status-dot');
  const radios = row.querySelectorAll('input[type=radio]');
  radios.forEach((r, i) => {
    const dot = dots[i];
    const color = Object.values(statusColors)[i];
    if (r.checked) {
      dot.className = dot.className.replace(/border-gray-200 text-gray-300/, '');
      dot.classList.add('border-current', color, 'bg-current/10');
    } else {
      Object.values(statusColors).forEach(c => dot.classList.remove(c, 'bg-current/10', 'border-current'));
      dot.classList.add('border-gray-200', 'text-gray-300');
    }
  });
}

function markAll(status) {
  document.querySelectorAll(`input[value="${status}"]`).forEach(r => {
    r.checked = true;
    updateRow(r);
  });
}
</script>
@endsection
