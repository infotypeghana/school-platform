@extends('layouts.admin')

@section('title', 'Student Promotion')
@section('page-title', 'Student Promotion')

@section('content')

<div class="max-w-4xl space-y-5">

    {{-- Header row --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.students') }}"
           class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            ← Students
        </a>
        <a href="{{ route('admin.promotion.history') }}"
           class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-500 hover:bg-gray-50 transition-colors ml-auto">
            📋 Promotion History
        </a>
    </div>

    {{-- Info banner --}}
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        <p class="font-semibold mb-1">End-of-year class promotion</p>
        <ul class="list-disc list-inside space-y-0.5 text-xs text-amber-700">
            <li>Only <strong>active</strong> students are shown.</li>
            <li>Every decision is recorded in the promotion history with the academic year and your name.</li>
            <li>Students already processed for the chosen academic year are flagged and excluded automatically.</li>
            <li>You can set a different action for each student — override the default per row.</li>
        </ul>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         STEP 1 — Configuration form (shown when no preview is loaded)
    ═══════════════════════════════════════════════════════════════ --}}
    @unless(isset($students))
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-5">Step 1 — Configure batch</h3>

        <form method="POST" action="{{ route('admin.promotion.preview') }}" class="space-y-5"
              x-data="{ defaultAction: 'promoted' }">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Academic Year *</label>
                    <select name="academic_year_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($academicYears as $year)
                            <option value="{{ $year->id }}"
                                    @selected($year->id === $currentYear?->id)>
                                {{ $year->year_label }}
                                @if($year->is_current) (Current) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">From class *</label>
                    <select name="from_class_id" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— select class —</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">
                                {{ $class->full_name }}
                                ({{ $class->students_count }} active)
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-2">Default action *</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="default_action" value="promoted"
                                   x-model="defaultAction" class="text-blue-600" checked>
                            Promote to next class
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="default_action" value="held_back"
                                   x-model="defaultAction" class="text-blue-600">
                            Hold back (repeat class)
                        </label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" name="default_action" value="graduated"
                                   x-model="defaultAction" class="text-blue-600">
                            Graduate (final class)
                        </label>
                    </div>
                </div>

                <div x-show="defaultAction === 'promoted'">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Default target class</label>
                    <select name="to_class_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">— select class (can override per student) —</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->full_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit"
                    class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Load Students →
            </button>
        </form>
    </div>
    @endunless

    {{-- ═══════════════════════════════════════════════════════════════
         STEP 2 — Per-student action table + confirm form
    ═══════════════════════════════════════════════════════════════ --}}
    @isset($students)

    {{-- Summary banner --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-4 flex flex-wrap items-center gap-4 text-sm">
        <div>
            <span class="text-gray-500">Class:</span>
            <strong class="text-gray-900 ml-1">{{ $fromClass->full_name }}</strong>
        </div>
        <div>
            <span class="text-gray-500">Academic Year:</span>
            <strong class="text-gray-900 ml-1">{{ $academicYear->year_label }}</strong>
        </div>
        <div>
            <span class="text-gray-500">Active students:</span>
            <strong class="text-gray-900 ml-1">{{ $students->count() }}</strong>
        </div>
        @if($alreadyPromotedIds->isNotEmpty())
        <div class="ml-auto">
            <span class="bg-amber-100 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full">
                ⚠ {{ $alreadyPromotedIds->count() }} already processed this year
            </span>
        </div>
        @endif
    </div>

    @if($students->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-10 text-center text-gray-400 text-sm">
            No active students in {{ $fromClass->full_name }}.
        </div>
        <a href="{{ route('admin.promotion') }}"
           class="inline-block px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
            ← Try another class
        </a>
    @else

    <form method="POST" action="{{ route('admin.promotion.execute') }}" id="promotionForm">
        @csrf
        <input type="hidden" name="from_class_id"    value="{{ $fromClass->id }}">
        <input type="hidden" name="academic_year_id" value="{{ $academicYear->id }}">

        {{-- Per-student table --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Student · Per-student action</p>
                {{-- Bulk-set all visible students --}}
                <div class="flex items-center gap-2 text-xs text-gray-500" x-data>
                    Set all to:
                    @foreach(['promoted' => 'Promote', 'held_back' => 'Hold Back', 'graduated' => 'Graduate', 'skip' => 'Skip'] as $val => $lbl)
                    <button type="button"
                            onclick="document.querySelectorAll('.action-select:not([disabled])').forEach(s => s.value = '{{ $val }}')"
                            class="underline hover:text-gray-800">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-gray-50">
                @foreach($students as $student)
                @php $alreadyDone = isset($alreadyPromotedIds[$student->id]); @endphp
                <div class="px-5 py-3 {{ $alreadyDone ? 'bg-amber-50 opacity-75' : 'hover:bg-gray-50' }} transition-colors"
                     x-data="{ action: '{{ $alreadyDone ? 'skip' : $defaultAction }}' }">
                    <div class="flex flex-wrap items-center gap-3">

                        {{-- Student info --}}
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 text-sm">{{ $student->full_name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ $student->admission_number }}</p>
                            @if($alreadyDone)
                                <p class="text-xs text-amber-600 font-medium mt-0.5">
                                    ⚠ Already processed for {{ $academicYear->year_label }} — will be skipped
                                </p>
                            @endif
                        </div>

                        {{-- Action selector --}}
                        <select name="students[{{ $student->id }}][action]"
                                x-model="action"
                                class="action-select text-sm border border-gray-300 rounded-lg px-2 py-1.5
                                       {{ $alreadyDone ? 'bg-gray-100 text-gray-400' : '' }}"
                                @if($alreadyDone) disabled @endif>
                            <option value="promoted"  @selected(!$alreadyDone && $defaultAction === 'promoted')>Promote</option>
                            <option value="held_back" @selected(!$alreadyDone && $defaultAction === 'held_back')>Hold Back</option>
                            <option value="graduated" @selected(!$alreadyDone && $defaultAction === 'graduated')>Graduate</option>
                            <option value="skip"      @selected($alreadyDone)>Skip</option>
                        </select>
                        {{-- Mirror value for disabled rows so the field is still submitted --}}
                        @if($alreadyDone)
                        <input type="hidden" name="students[{{ $student->id }}][action]" value="skip">
                        @endif

                        {{-- Target class (only relevant when action = promoted) --}}
                        <select name="students[{{ $student->id }}][to_class_id]"
                                x-show="action === 'promoted'"
                                class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 min-w-36"
                                @if($alreadyDone) disabled @endif>
                            <option value="">— target class —</option>
                            @foreach($classes as $cls)
                                <option value="{{ $cls->id }}"
                                        @selected($cls->id === $toClass?->id && $cls->id !== $fromClass->id)>
                                    {{ $cls->full_name }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Notes toggle --}}
                        <div x-data="{ showNotes: false }" class="flex items-center gap-1" x-show="!{{ $alreadyDone ? 'true' : 'false' }}">
                            <button type="button" @click="showNotes = !showNotes"
                                    class="text-xs text-gray-400 hover:text-gray-600 underline">
                                note
                            </button>
                            <input x-show="showNotes" x-transition
                                   type="text" name="students[{{ $student->id }}][notes]"
                                   placeholder="Optional note…"
                                   class="text-xs border border-gray-200 rounded px-2 py-1 w-44">
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Confirm + submit --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mt-4">
            <label class="flex items-start gap-3 cursor-pointer mb-4">
                <input type="checkbox" id="confirmCheck" required
                       class="mt-0.5 rounded border-gray-300 text-blue-600">
                <span class="text-sm text-gray-700">
                    I confirm this promotion batch for <strong>{{ $fromClass->full_name }}</strong>
                    in academic year <strong>{{ $academicYear->year_label }}</strong>.
                    Each student's action above has been reviewed. This will update student records
                    and write a permanent promotion history entry.
                </span>
            </label>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.promotion') }}"
                   class="px-5 py-2.5 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                    ← Start over
                </a>
                <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                    Execute Promotion Batch
                </button>
            </div>
        </div>
    </form>

    @endif {{-- $students->isNotEmpty() --}}
    @endisset

</div>
@endsection

@push('scripts')
<script>
// Keep the confirm checkbox enforced even if user tries to bypass
document.getElementById('promotionForm')?.addEventListener('submit', function (e) {
    if (!document.getElementById('confirmCheck')?.checked) {
        e.preventDefault();
        alert('Please tick the confirmation checkbox before proceeding.');
    }
});
</script>
@endpush
