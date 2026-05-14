@extends('layouts.admin')

@section('title', 'Admission — ' . $admission->full_name)
@section('page-title', 'Admission Application')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    {{-- ── Breadcrumb ───────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.admissions.index') }}" class="hover:text-blue-600 transition-colors">Admissions</a>
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-900 font-medium">{{ $admission->full_name }}</span>
    </div>

    {{-- ── Status card ──────────────────────────────────────────────────── --}}
    @php
        $statusConfig = match($admission->status) {
            'enrolled' => ['bg' => 'bg-blue-50',    'border' => 'border-blue-200',    'text' => 'text-blue-800',    'badge' => 'bg-blue-100 text-blue-700',       'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
            'accepted' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'badge' => 'bg-emerald-100 text-emerald-700', 'icon' => 'M5 13l4 4L19 7'],
            'rejected' => ['bg' => 'bg-red-50',     'border' => 'border-red-200',     'text' => 'text-red-800',     'badge' => 'bg-red-100 text-red-700',         'icon' => 'M6 18L18 6M6 6l12 12'],
            default    => ['bg' => 'bg-amber-50',   'border' => 'border-amber-200',   'text' => 'text-amber-800',   'badge' => 'bg-amber-100 text-amber-700',     'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        };
    @endphp

    <div class="{{ $statusConfig['bg'] }} {{ $statusConfig['border'] }} border rounded-xl px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full {{ $statusConfig['badge'] }} flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $statusConfig['icon'] }}"/>
                </svg>
            </div>
            <div>
                <p class="{{ $statusConfig['text'] }} font-semibold text-sm">
                    Status: <span class="capitalize">{{ $admission->status }}</span>
                </p>
                <p class="text-xs {{ $statusConfig['text'] }} opacity-75 mt-0.5">
                    Submitted {{ $admission->submitted_at?->format('d M Y \a\t H:i') ?? $admission->created_at->format('d M Y \a\t H:i') }}
                    @if($admission->enrolled_at)
                        &nbsp;·&nbsp; Enrolled {{ $admission->enrolled_at->format('d M Y') }}
                    @endif
                </p>
            </div>
        </div>

        {{-- ── Pending: Accept / Reject ─────────────────────────────────── --}}
        @if($admission->status === 'pending')
            <div class="flex gap-2 flex-shrink-0" x-data="{ rejectModal: false }">
                <form method="POST" action="{{ route('admin.admissions.accept', $admission) }}">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Accept
                    </button>
                </form>
                <button type="button" @click="rejectModal = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reject
                </button>

                {{-- Reject Modal --}}
                <div x-show="rejectModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="rejectModal = false">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="rejectModal = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4" @click.stop>
                        <h3 class="text-base font-semibold text-gray-900">Reject Application</h3>
                        <form method="POST" action="{{ route('admin.admissions.reject', $admission) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reason (optional, internal)</label>
                                <textarea name="rejection_reason" rows="3"
                                          placeholder="e.g. Class is full for this term…"
                                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 resize-none"></textarea>
                            </div>
                            <div class="flex gap-3 pt-1">
                                <button type="submit"
                                        class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                                    Confirm Rejection
                                </button>
                                <button type="button" @click="rejectModal = false"
                                        class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        {{-- ── Accepted (not yet enrolled): primary CTA ─────────────────── --}}
        @elseif($admission->status === 'accepted')
            <a href="{{ route('admin.admissions.enroll.form', $admission) }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700
                      text-white text-sm font-semibold rounded-lg transition-colors shadow-sm flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Enroll as Student
            </a>

        {{-- ── Enrolled: link to student profile ───────────────────────── --}}
        @elseif($admission->status === 'enrolled' && $admission->student_id)
            <a href="{{ route('admin.students.show', $admission->student_id) }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700
                      text-white text-sm font-semibold rounded-lg transition-colors shadow-sm flex-shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                View Student Profile
            </a>
        @endif
    </div>

    {{-- ── Main details ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Student Information --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <h2 class="text-sm font-semibold text-gray-900">Student Information</h2>
            </div>
            <dl class="divide-y divide-gray-50">
                @foreach([
                    ['First Name',         $admission->first_name],
                    ['Last Name',          $admission->last_name],
                    ['Date of Birth',      $admission->date_of_birth?->format('d F Y') ?? '—'],
                    ['Gender',             ucfirst($admission->gender ?? '—')],
                    ['Class Applying For', $admission->class_applying_for ?? '—'],
                    ['Previous School',    $admission->previous_school ?: '—'],
                ] as [$label, $value])
                    <div class="px-5 py-3 flex justify-between gap-4">
                        <dt class="text-xs font-medium text-gray-500 flex-shrink-0">{{ $label }}</dt>
                        <dd class="text-sm text-gray-800 text-right">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Guardian Information --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <h2 class="text-sm font-semibold text-gray-900">Guardian / Contact</h2>
            </div>
            <dl class="divide-y divide-gray-50">
                @foreach([
                    ['Guardian Name',  $admission->guardian_name ?? '—'],
                    ['Phone Number',   $admission->guardian_phone ?? '—'],
                    ['Email Address',  $admission->guardian_email ?: '—'],
                    ['Home Address',   $admission->address ?: '—'],
                    ['Term Applied',   $admission->term?->term_name . ' (' . ($admission->term?->academicYear?->year_label ?? '') . ')'],
                ] as [$label, $value])
                    <div class="px-5 py-3 flex justify-between gap-4">
                        <dt class="text-xs font-medium text-gray-500 flex-shrink-0">{{ $label }}</dt>
                        <dd class="text-sm text-gray-800 text-right">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    {{-- Notes / Rejection reason --}}
    @if($admission->notes)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">
                {{ $admission->status === 'rejected' ? 'Rejection Reason' : 'Notes' }}
            </h2>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $admission->notes }}</p>
        </div>
    @endif

    {{-- ── Footer actions ────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between pt-2">
        <a href="{{ route('admin.admissions.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Admissions
        </a>
        <form method="POST" action="{{ route('admin.admissions.destroy', $admission) }}"
              onsubmit="return confirm('Delete this application permanently? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 text-sm text-red-500 hover:text-red-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Application
            </button>
        </form>
    </div>
</div>
@endsection
