@extends('layouts.admin')

@section('title', 'Timetable')
@section('page-title', 'Timetable')

@section('content')

{{-- Class selector --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
  <div>
    <label class="block text-xs font-medium text-gray-500 mb-1">Class</label>
    <select name="class_id" onchange="this.form.submit()"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 min-w-[180px]">
      @foreach($classes as $c)
        <option value="{{ $c->id }}" {{ $class?->id == $c->id ? 'selected' : '' }}>
          {{ $c->full_name }}
        </option>
      @endforeach
    </select>
  </div>
  @if($class)
    <a href="{{ route('admin.timetables.create', ['class_id' => $class->id]) }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Add Period
    </a>
  @endif
</form>

@if(! $class)
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center text-gray-400 text-sm">
    Select a class above to view or edit its timetable.
  </div>
@elseif($entries->isEmpty())
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
    <p class="text-gray-500 text-sm mb-4">No timetable entries yet for <strong>{{ $class->full_name }}</strong>.</p>
    <a href="{{ route('admin.timetables.create', ['class_id' => $class->id]) }}"
       class="inline-flex items-center gap-1.5 bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
      Add first period
    </a>
  </div>
@else
  {{-- Weekly grid --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-x-auto">
    <table class="w-full text-sm border-collapse">
      <thead>
        <tr class="bg-gray-50">
          <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 border-b border-gray-200 w-16">Period</th>
          @foreach(\App\Models\Timetable::DAYS as $num => $day)
            <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 border-b border-gray-200">{{ $day }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @for($period = 1; $period <= $maxPeriod; $period++)
          <tr class="border-b border-gray-100 hover:bg-gray-50/50">
            <td class="px-3 py-2 text-xs font-medium text-gray-500 align-top">
              <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-gray-600 font-semibold">
                {{ $period }}
              </span>
            </td>
            @foreach(\App\Models\Timetable::DAYS as $day => $dayName)
              @php $slot = $entries->get("{$day}_{$period}"); @endphp
              <td class="px-2 py-2 align-top border-l border-gray-100 min-w-[140px]">
                @if($slot)
                  <div class="rounded-lg p-2 {{ $slot->subject_id ? 'bg-blue-50 border border-blue-100' : 'bg-gray-50 border border-gray-200' }} group relative">
                    <p class="font-semibold text-gray-800 text-xs leading-tight">
                      {{ $slot->label ?: $slot->subject?->name ?: '—' }}
                    </p>
                    @if($slot->teacher)
                      <p class="text-xs text-gray-500 mt-0.5">{{ $slot->teacher->full_name }}</p>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5">{{ $slot->time_range }}</p>
                    <div class="absolute top-1 right-1 hidden group-hover:flex gap-1">
                      <a href="{{ route('admin.timetables.edit', $slot) }}"
                         class="p-0.5 rounded bg-white border border-gray-200 text-blue-600 hover:text-blue-800">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </a>
                      <form method="POST" action="{{ route('admin.timetables.destroy', $slot) }}"
                            onsubmit="return confirm('Remove this period?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-0.5 rounded bg-white border border-gray-200 text-red-500 hover:text-red-700">
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                          </svg>
                        </button>
                      </form>
                    </div>
                  </div>
                @else
                  <a href="{{ route('admin.timetables.create', ['class_id' => $class->id, 'day' => $day, 'period' => $period]) }}"
                     class="flex items-center justify-center h-12 rounded-lg border-2 border-dashed border-gray-200 text-gray-300 hover:border-blue-300 hover:text-blue-400 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                  </a>
                @endif
              </td>
            @endforeach
          </tr>
        @endfor
      </tbody>
    </table>
  </div>
@endif

@endsection
