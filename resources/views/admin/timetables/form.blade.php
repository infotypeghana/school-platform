@extends('layouts.admin')

@section('title', isset($timetable) ? 'Edit Period' : 'Add Period')
@section('page-title', isset($timetable) ? 'Edit Period' : 'Add Period')

@section('content')
<div class="max-w-xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    @php
      $action  = isset($timetable) ? route('admin.timetables.update', $timetable) : route('admin.timetables.store');
      $method  = isset($timetable) ? 'PUT' : 'POST';
      $classId = old('school_class_id', $timetable->school_class_id ?? $classId);
      $day     = old('day_of_week',   $timetable->day_of_week   ?? $dayOfWeek);
      $period  = old('period_number', $timetable->period_number ?? $periodNumber);
    @endphp

    <form method="POST" action="{{ $action }}"
          x-data="timetableForm({{ $classId ?? 'null' }})"
          x-init="init()">
      @csrf
      @if($method === 'PUT') @method('PUT') @endif

      {{-- Class --}}
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
        <select name="school_class_id" x-model="selectedClass" @change="loadSubjects()"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" required>
          <option value="">— Select class —</option>
          @foreach($classes as $c)
            <option value="{{ $c->id }}" {{ $classId == $c->id ? 'selected' : '' }}>
              {{ $c->full_name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Day & Period --}}
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Day *</label>
          <select name="day_of_week"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" required>
            @foreach(\App\Models\Timetable::DAYS as $num => $name)
              <option value="{{ $num }}" {{ $day == $num ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period # *</label>
          <input type="number" name="period_number" min="1" max="9"
                 value="{{ old('period_number', $period ?? '') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>

      {{-- Time --}}
      <div class="grid grid-cols-2 gap-4 mb-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
          <input type="time" name="start_time"
                 value="{{ old('start_time', isset($timetable) ? substr($timetable->start_time, 0, 5) : '07:30') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
          <input type="time" name="end_time"
                 value="{{ old('end_time', isset($timetable) ? substr($timetable->end_time, 0, 5) : '08:15') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>

      {{-- Subject --}}
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
        <select name="subject_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— None / Free period —</option>
          @foreach($subjects as $sub)
            <option value="{{ $sub->id }}"
              {{ old('subject_id', $timetable->subject_id ?? '') == $sub->id ? 'selected' : '' }}>
              {{ $sub->name }}
            </option>
          @endforeach
          <template x-for="sub in ajaxSubjects" :key="sub.id">
            <option :value="sub.id" x-text="sub.name"></option>
          </template>
        </select>
        <p class="text-xs text-gray-400 mt-1">Subjects are filtered by the selected class.</p>
      </div>

      {{-- Teacher --}}
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1">Teacher</label>
        <select name="teacher_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— None —</option>
          @foreach($teachers as $t)
            <option value="{{ $t->id }}"
              {{ old('teacher_id', $timetable->teacher_id ?? '') == $t->id ? 'selected' : '' }}>
              {{ $t->full_name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Label override --}}
      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Label Override <span class="text-gray-400 font-normal">(optional)</span></label>
        <input type="text" name="label" maxlength="80"
               value="{{ old('label', $timetable->label ?? '') }}"
               placeholder="e.g. Break, Assembly, Games"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">If set, this text shows instead of the subject name on the grid.</p>
      </div>

      <div class="flex gap-3">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          {{ isset($timetable) ? 'Save Changes' : 'Add Period' }}
        </button>
        <a href="{{ route('admin.timetables.index', ['class_id' => $classId]) }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function timetableForm(initialClassId) {
    return {
        selectedClass: initialClassId ? String(initialClassId) : '',
        ajaxSubjects: [],
        init() {
            // subjects already rendered server-side; only need AJAX when class changes
        },
        loadSubjects() {
            if (! this.selectedClass) { this.ajaxSubjects = []; return; }
            fetch(`/timetables/subjects/${this.selectedClass}`)
                .then(r => r.json())
                .then(data => { this.ajaxSubjects = data; });
        },
    };
}
</script>
@endpush

@endsection
