@extends('layouts.admin')

@section('title', $note->title)
@section('page-title', 'Lesson Note')

@section('content')
<div class="space-y-6 max-w-4xl">

    {{-- Back + Actions --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.lesson-notes.index') }}"
           class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
            ← Back to Lesson Notes
        </a>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $note->statusBadgeClass() }}">
            {{ $note->statusLabel() }}
        </span>
    </div>

    {{-- Approval panel (shown for submitted notes) --}}
    @if($note->status === 'submitted')
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 space-y-4">
        <h3 class="font-semibold text-blue-900">Review This Lesson Note</h3>
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Approve --}}
            <form method="POST" action="{{ route('admin.lesson-notes.approve', $note->id) }}">
                @csrf
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors"
                        onclick="return confirm('Approve this lesson note?')">
                    ✓ Approve
                </button>
            </form>

            {{-- Request revision --}}
            <button @click="$el.closest('.space-y-4').querySelector('.revision-form').classList.toggle('hidden')"
                    class="bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors"
                    x-data>
                ↩ Request Revision
            </button>
        </div>

        <div class="revision-form hidden">
            <form method="POST" action="{{ route('admin.lesson-notes.revision', $note->id) }}" class="space-y-3">
                @csrf
                <textarea name="revision_notes" rows="3" required
                          placeholder="Describe what needs to be corrected or improved…"
                          class="w-full text-sm border border-amber-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-amber-400 focus:outline-none"></textarea>
                <button type="submit"
                        class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium px-4 py-2 rounded-lg">
                    Send Revision Request
                </button>
            </form>
        </div>
    </div>
    @endif

    {{-- Revision notes (if any) --}}
    @if($note->revision_notes)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-xs font-semibold text-amber-700 mb-1">REVISION NOTES</p>
        <p class="text-sm text-amber-900">{{ $note->revision_notes }}</p>
    </div>
    @endif

    {{-- Approved by --}}
    @if($note->status === 'approved' && $note->approvedBy)
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <p class="text-sm text-green-800">
            ✓ Approved by <strong>{{ $note->approvedBy->name }}</strong>
            on {{ $note->approved_at?->format('d M Y, g:i A') }}
        </p>
    </div>
    @endif

    {{-- Header card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-gray-900 mb-1">{{ $note->title }}</h2>
        <p class="text-sm text-gray-500 mb-4">{{ ucfirst(str_replace('_', ' ', $note->type)) }}</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Teacher</p>
                <p class="text-gray-800 mt-0.5">{{ $note->teacher?->full_name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Class</p>
                <p class="text-gray-800 mt-0.5">{{ $note->schoolClass?->name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Subject</p>
                <p class="text-gray-800 mt-0.5">{{ $note->subject?->name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Term</p>
                <p class="text-gray-800 mt-0.5">{{ $note->term?->term_name }} — {{ $note->term?->academicYear?->year_label }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Lesson Date</p>
                <p class="text-gray-800 mt-0.5">{{ $note->lesson_date?->format('d M Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Day / Period</p>
                <p class="text-gray-800 mt-0.5">{{ $note->day_of_week ?? '—' }} {{ $note->period ? "· Period {$note->period}" : '' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Duration</p>
                <p class="text-gray-800 mt-0.5">{{ $note->duration ? $note->duration . ' min' : '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Week Ending</p>
                <p class="text-gray-800 mt-0.5">{{ $note->week_ending?->format('d M Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Curriculum info --}}
    @if($note->strand || $note->content_standard || $note->indicator)
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-3">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Curriculum Reference</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            @if($note->strand)
            <div>
                <p class="text-xs text-gray-400 font-medium">Strand</p>
                <p class="text-gray-800 mt-0.5">{{ $note->strand->name }}</p>
            </div>
            @endif
            @if($note->subStrand)
            <div>
                <p class="text-xs text-gray-400 font-medium">Sub-Strand</p>
                <p class="text-gray-800 mt-0.5">{{ $note->subStrand->name }}</p>
            </div>
            @endif
            @if($note->content_standard)
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-400 font-medium">Content Standard</p>
                <p class="text-gray-800 mt-0.5">{{ $note->content_standard }}</p>
            </div>
            @endif
            @if($note->indicator_code)
            <div>
                <p class="text-xs text-gray-400 font-medium">Indicator Code</p>
                <p class="text-gray-800 mt-0.5 font-mono text-xs">{{ $note->indicator_code }}</p>
            </div>
            @endif
            @if($note->indicator)
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-400 font-medium">Indicator</p>
                <p class="text-gray-800 mt-0.5">{{ $note->indicator }}</p>
            </div>
            @endif
            @if($note->performance_indicator)
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-400 font-medium">Performance Indicator</p>
                <p class="text-gray-800 mt-0.5">{{ $note->performance_indicator }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Competencies + Resources --}}
    @if($note->core_competencies || $note->keywords || $note->reference_materials || $note->tlr)
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Resources & Competencies</h3>
        @if($note->core_competencies && count($note->coreCompetencyLabels()))
        <div>
            <p class="text-xs text-gray-400 font-medium mb-2">Core Competencies</p>
            <div class="flex flex-wrap gap-2">
                @foreach($note->coreCompetencyLabels() as $cc)
                    <span class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-full border border-blue-100">{{ $cc }}</span>
                @endforeach
            </div>
        </div>
        @endif
        @foreach([
            'reference_materials' => 'Reference Materials',
            'tlr'                 => 'Teaching & Learning Resources (TLR)',
            'keywords'            => 'Keywords',
        ] as $field => $label)
            @if($note->$field)
            <div>
                <p class="text-xs text-gray-400 font-medium">{{ $label }}</p>
                <p class="text-sm text-gray-800 mt-0.5">{{ $note->$field }}</p>
            </div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Lesson body --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Lesson Body</h3>
        @foreach([
            'starter'         => 'Starter / Introduction',
            'main_activities' => 'Main Activities',
            'assessment'      => 'Assessment',
            'conclusion'      => 'Conclusion / Closure',
            'homework'        => 'Homework / Follow-up',
        ] as $field => $label)
            @if($note->$field)
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ $label }}</p>
                <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap border-l-2 border-gray-200 pl-3">{{ $note->$field }}</div>
            </div>
            @endif
        @endforeach
    </div>

    {{-- Attachments --}}
    @if($note->attachments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Attachments</h3>
        <div class="space-y-2">
            @foreach($note->attachments as $att)
            <a href="{{ $att->publicUrl() }}" target="_blank"
               class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors">
                <span class="text-2xl">{{ $att->typeIcon() }}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">{{ $att->original_name }}</p>
                    <p class="text-xs text-gray-400">{{ $att->humanSize() }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection
