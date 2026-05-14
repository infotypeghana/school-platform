@extends('layouts.admin')

@section('title', 'Enrollment — ' . $device->name)
@section('page-title', 'Enrollment Map — ' . $device->name)

@section('content')

<div class="flex items-center gap-3 mb-6">
  <a href="{{ route('admin.biometric.index') }}"
     class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Devices</a>
  <h3 class="text-sm font-semibold text-gray-700">
    Map device user IDs to students / teachers
  </h3>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

  {{-- Add mapping --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Add Mapping</h3>

    @if($unmatchedIds->isNotEmpty())
      <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3 text-xs">
        <p class="font-semibold mb-1">{{ $unmatchedIds->count() }} unmatched device user ID(s):</p>
        <div class="flex flex-wrap gap-1">
          @foreach($unmatchedIds as $uid)
            <code class="bg-amber-100 px-2 py-0.5 rounded text-amber-900 cursor-pointer"
                  onclick="document.getElementById('device_user_id').value='{{ $uid }}'">
              {{ $uid }}
            </code>
          @endforeach
        </div>
        <p class="mt-2 text-amber-700">Click an ID to auto-fill the form below.</p>
      </div>
    @else
      <p class="text-xs text-gray-400 mb-4">No unmatched IDs — all device users are mapped.</p>
    @endif

    <form method="POST" action="{{ route('admin.biometric.enroll.store', $device) }}" class="space-y-3">
      @csrf
      @if($errors->any())
        <div class="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
          @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
      @endif

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Device User ID *</label>
        <input type="text" id="device_user_id" name="device_user_id"
               value="{{ old('device_user_id') }}" required
               placeholder="e.g. 1, 42, 117"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">The integer user ID stored on the ZKTeco device.</p>
      </div>

      <div x-data="{ type: '{{ old('person_type', 'student') }}' }">
        <label class="block text-xs font-medium text-gray-600 mb-1">Map to *</label>
        <div class="flex gap-3 mb-3">
          <label class="flex items-center gap-1.5 text-sm cursor-pointer">
            <input type="radio" name="person_type" value="student" x-model="type"
                   class="text-blue-600" {{ old('person_type', 'student') === 'student' ? 'checked' : '' }}>
            Student
          </label>
          <label class="flex items-center gap-1.5 text-sm cursor-pointer">
            <input type="radio" name="person_type" value="teacher" x-model="type"
                   class="text-blue-600" {{ old('person_type') === 'teacher' ? 'checked' : '' }}>
            Teacher
          </label>
        </div>

        <div x-show="type === 'student'">
          <select name="person_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">— select student —</option>
            @foreach($students as $student)
              <option value="{{ $student->id }}" {{ old('person_id') == $student->id ? 'selected' : '' }}>
                {{ $student->full_name }} ({{ $student->schoolClass?->full_name ?? '—' }})
              </option>
            @endforeach
          </select>
        </div>
        <div x-show="type === 'teacher'" x-cloak>
          <select name="person_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">— select teacher —</option>
            @foreach($teachers as $teacher)
              <option value="{{ $teacher->id }}" {{ old('person_id') == $teacher->id ? 'selected' : '' }}>
                {{ $teacher->full_name }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
        Save Mapping
      </button>
    </form>
  </div>

  {{-- Existing enrollments --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
      <h3 class="text-sm font-semibold text-gray-700">Existing Mappings ({{ $enrollments->count() }})</h3>
    </div>
    @if($enrollments->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">No mappings yet.</p>
    @else
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Device User ID</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Person</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Type</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @foreach($enrollments as $enrollment)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 font-mono text-xs text-gray-700 font-semibold">{{ $enrollment->device_user_id }}</td>
              <td class="px-4 py-2 text-gray-700 text-xs">{{ $enrollment->person_name }}</td>
              <td class="px-4 py-2 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                  {{ $enrollment->person_type === 'student' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                  {{ ucfirst($enrollment->person_type) }}
                </span>
              </td>
              <td class="px-4 py-2 text-right">
                <form method="POST" action="{{ route('admin.biometric.enroll.destroy', $enrollment) }}"
                      onsubmit="return confirm('Remove this mapping?')" class="inline">
                  @csrf @method('DELETE')
                  <button class="text-xs text-red-400 hover:text-red-600">Remove</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

</div>
@endsection
