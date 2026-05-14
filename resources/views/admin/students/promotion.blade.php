@extends('layouts.admin')

@section('title', 'Student Promotion')
@section('page-title', 'Student Promotion')

@section('content')

<div class="max-w-2xl">

  <div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.students') }}"
       class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Students</a>
    <h3 class="text-sm font-semibold text-gray-700">End-of-year class promotion</h3>
  </div>

  @if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
  @endif

  {{-- Info banner --}}
  <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm text-amber-800">
    <p class="font-semibold mb-1">Use this at the start of a new academic year.</p>
    <ul class="list-disc list-inside space-y-0.5 text-xs text-amber-700">
      <li>Only <strong>active</strong> students are affected.</li>
      <li><strong>Promote</strong> moves students to the next class (e.g. Basic 4 → Basic 5).</li>
      <li><strong>Graduate</strong> marks students as graduated — for the final class (e.g. JHS 3).</li>
      <li>A preview shows exactly who will be affected before confirming.</li>
    </ul>
  </div>

  {{-- ── Step 1: Select action ─────────────────────────────────────────────── --}}
  @if(!isset($students))
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Step 1 — Configure promotion</h3>

    <form method="POST" action="{{ route('admin.promotion.preview') }}" class="space-y-4"
          x-data="{ action: 'promote' }">
      @csrf

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">From class *</label>
        <select name="from_class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— select current class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}" {{ old('from_class_id') == $class->id ? 'selected' : '' }}>
              {{ $class->full_name }}
              ({{ $class->students_count }} active student{{ $class->students_count !== 1 ? 's' : '' }})
            </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-2">Action *</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="action" value="promote" x-model="action"
                   class="text-blue-600" checked>
            <span>Promote to next class</span>
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="radio" name="action" value="graduate" x-model="action"
                   class="text-blue-600">
            <span>Graduate (final class)</span>
          </label>
        </div>
      </div>

      <div x-show="action === 'promote'">
        <label class="block text-xs font-medium text-gray-600 mb-1">To class *</label>
        <select name="to_class_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— select target class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}" {{ old('to_class_id') == $class->id ? 'selected' : '' }}>
              {{ $class->full_name }}
            </option>
          @endforeach
        </select>
      </div>

      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
        Preview Changes →
      </button>
    </form>
  </div>
  @endif

  {{-- ── Step 2: Preview & confirm ─────────────────────────────────────────── --}}
  @isset($students)
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
      <h3 class="text-sm font-semibold text-gray-700">
        Step 2 — Preview
        @if($action === 'promote')
          <span class="font-normal text-gray-500">
            {{ $fromClass->full_name }} → {{ $toClass?->full_name }}
          </span>
        @else
          <span class="font-normal text-gray-500">
            Graduate {{ $fromClass->full_name }}
          </span>
        @endif
      </h3>
    </div>

    @if($students->isEmpty())
      <p class="px-5 py-8 text-sm text-gray-400 text-center">
        No active students in {{ $fromClass->full_name }}.
      </p>
    @else
      <div class="px-5 py-3 border-b border-gray-100 bg-amber-50">
        <p class="text-xs text-amber-800">
          <strong>{{ $students->count() }} student(s)</strong> will be
          @if($action === 'promote')
            moved to <strong>{{ $toClass?->full_name }}</strong>.
          @else
            marked as <strong>graduated</strong>.
          @endif
          This cannot be undone automatically.
        </p>
      </div>

      <div class="max-h-72 overflow-y-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 sticky top-0">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Student</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Admission #</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Current Class</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            @foreach($students as $student)
              <tr>
                <td class="px-4 py-2 font-medium text-gray-800">{{ $student->full_name }}</td>
                <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $student->admission_number }}</td>
                <td class="px-4 py-2 text-gray-600">{{ $fromClass->full_name }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  @if($students->isNotEmpty())
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <form method="POST" action="{{ route('admin.promotion.execute') }}" class="space-y-4">
      @csrf
      <input type="hidden" name="from_class_id" value="{{ $fromClass->id }}">
      <input type="hidden" name="to_class_id"   value="{{ $toClass?->id }}">
      <input type="hidden" name="action"         value="{{ $action }}">

      <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="confirmed" value="1" required
               class="mt-0.5 rounded border-gray-300 text-blue-600">
        <span class="text-sm text-gray-700">
          I confirm that I want to
          @if($action === 'promote')
            promote <strong>{{ $students->count() }}</strong> student(s) from
            <strong>{{ $fromClass->full_name }}</strong> to
            <strong>{{ $toClass?->full_name }}</strong>.
          @else
            graduate <strong>{{ $students->count() }}</strong> student(s) from
            <strong>{{ $fromClass->full_name }}</strong>.
          @endif
          This action affects live student records.
        </span>
      </label>

      <div class="flex gap-3">
        <a href="{{ route('admin.promotion') }}"
           class="flex-1 text-center px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          ← Start over
        </a>
        <button type="submit"
                class="flex-1 bg-{{ $action === 'graduate' ? 'amber' : 'blue' }}-600 hover:bg-{{ $action === 'graduate' ? 'amber' : 'blue' }}-700 text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
          @if($action === 'promote')
            Confirm Promotion
          @else
            Confirm Graduation
          @endif
        </button>
      </div>
    </form>
  </div>
  @else
  <a href="{{ route('admin.promotion') }}"
     class="block text-center px-4 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
    ← Back to promotion form
  </a>
  @endif
  @endisset

</div>
@endsection
