@extends('layouts.admin')

@section('title', 'Enroll — ' . $admission->full_name)
@section('page-title', 'Enroll as Student')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- ── Breadcrumb ───────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.admissions.index') }}" class="hover:text-blue-600 transition-colors">Admissions</a>
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <a href="{{ route('admin.admissions.show', $admission) }}" class="hover:text-blue-600 transition-colors">
            {{ $admission->full_name }}
        </a>
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-900 font-medium">Enroll as Student</span>
    </div>

    {{-- ── Info banner ──────────────────────────────────────────────────── --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-sm text-blue-800">
            <p class="font-semibold mb-0.5">Review before enrolling</p>
            <p>The form below is pre-filled from the admission application. Correct any mistakes, then select the actual class
               and click <strong>Create Student Record</strong>. An admission number will be generated automatically.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

        {{-- ── Left: Admission summary (read-only reference) ─────────────── --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414
                                 a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Admission Application
                </h2>
            </div>
            <dl class="divide-y divide-gray-50 text-sm">
                @foreach([
                    ['Applicant',      $admission->full_name],
                    ['Date of Birth',  $admission->date_of_birth?->format('d M Y') ?? '—'],
                    ['Gender',         ucfirst($admission->gender ?? '—')],
                    ['Class Applying', $admission->class_applying_for ?? '—'],
                    ['Previous School',$admission->previous_school ?: '—'],
                    ['Guardian',       $admission->guardian_name],
                    ['Guardian Phone', $admission->guardian_phone],
                    ['Guardian Email', $admission->guardian_email ?: '—'],
                    ['Address',        $admission->address ?: '—'],
                    ['Term',           ($admission->term?->term_name ?? '—') . ' ' . ($admission->term?->academicYear?->year_label ?? '')],
                    ['Submitted',      $admission->submitted_at?->format('d M Y') ?? $admission->created_at->format('d M Y')],
                ] as [$label, $value])
                    <div class="px-5 py-2.5 flex justify-between gap-3">
                        <dt class="text-xs font-medium text-gray-400 flex-shrink-0 pt-0.5">{{ $label }}</dt>
                        <dd class="text-gray-800 text-right text-xs">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- ── Right: Student form ─────────────────────────────────────── --}}
        <div class="lg:col-span-3">
            <form method="POST" action="{{ route('admin.admissions.enroll', $admission) }}"
                  class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                @csrf

                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0z
                                     M3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        New Student Record
                    </h2>
                    <p class="text-xs text-gray-400 mt-0.5">Admission number will be auto-generated on save</p>
                </div>

                <div class="px-6 py-5 space-y-5">

                    {{-- Validation errors --}}
                    @if($errors->any())
                        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Name row --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                First Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="first_name"
                                   value="{{ old('first_name', $admission->first_name) }}"
                                   required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                          @error('first_name') border-red-400 @enderror">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                Last Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="last_name"
                                   value="{{ old('last_name', $admission->last_name) }}"
                                   required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                          @error('last_name') border-red-400 @enderror">
                        </div>
                    </div>

                    {{-- Class selector (required — map from text to actual FK) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            Assign to Class <span class="text-red-500">*</span>
                        </label>
                        <select name="school_class_id" required
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                       focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                                       @error('school_class_id') border-red-400 @enderror">
                            <option value="">— Select class —</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}"
                                    {{ old('school_class_id') == $class->id ? 'selected' : '' }}>
                                    {{ $class->name }}{{ $class->section ? " ({$class->section})" : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if($admission->class_applying_for)
                            <p class="text-xs text-gray-400 mt-1">
                                Applied for: <em>{{ $admission->class_applying_for }}</em>
                            </p>
                        @endif
                    </div>

                    {{-- DOB + Gender --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth"
                                   value="{{ old('date_of_birth', $admission->date_of_birth?->format('Y-m-d')) }}"
                                   max="{{ now()->subDay()->format('Y-m-d') }}"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Gender</label>
                            <select name="gender"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                           focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">— Select —</option>
                                <option value="male"   {{ old('gender', $admission->gender) === 'male'   ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender', $admission->gender) === 'female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                    </div>

                    {{-- Admission date --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Admission Date</label>
                        <input type="date" name="admission_date"
                               value="{{ old('admission_date', now()->format('Y-m-d')) }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                      focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    {{-- Guardian details --}}
                    <div class="pt-2 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Guardian / Contact</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Guardian Name</label>
                                <input type="text" name="guardian_name"
                                       value="{{ old('guardian_name', $admission->guardian_name) }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                              focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Phone</label>
                                    <input type="text" name="guardian_phone"
                                           value="{{ old('guardian_phone', $admission->guardian_phone) }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                                  focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" name="guardian_email"
                                           value="{{ old('guardian_email', $admission->guardian_email) }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                                  focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Home Address</label>
                                <textarea name="address" rows="2"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm
                                                 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none">{{ old('address', $admission->address) }}</textarea>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Submit bar --}}
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between gap-4">
                    <a href="{{ route('admin.admissions.show', $admission) }}"
                       class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        ← Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700
                                   text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0z
                                     M3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                        Create Student Record
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
