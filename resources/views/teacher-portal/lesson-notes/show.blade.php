@extends('layouts.teacher-portal')

@section('title', $note->title)

@section('content')
<div class="space-y-5 max-w-3xl mx-auto">

    {{-- Back + Actions --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('teacher.portal.lesson-notes.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700">← My Lesson Notes</a>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $note->statusBadgeClass() }}">
                {{ $note->statusLabel() }}
            </span>
            @if(in_array($note->status, ['draft', 'revision_requested']))
                <a href="{{ route('teacher.portal.lesson-notes.edit', $note->id) }}"
                   class="text-sm text-gray-600 hover:text-gray-900 font-medium">Edit</a>
            @endif
            @if(in_array($note->status, ['draft', 'revision_requested']))
                <form method="POST" action="{{ route('teacher.portal.lesson-notes.submit', $note->id) }}"
                      onsubmit="return confirm('Submit this lesson note for approval?')">
                    @csrf
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium px-4 py-2 rounded-lg">
                        Submit
                    </button>
                </form>
            @endif
            <button onclick="window.print()"
                    class="text-sm text-gray-400 hover:text-gray-600" title="Print">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Revision notice --}}
    @if($note->status === 'revision_requested' && $note->revision_notes)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-xs font-semibold text-amber-700 mb-1">REVISION REQUESTED</p>
        <p class="text-sm text-amber-900">{{ $note->revision_notes }}</p>
        <a href="{{ route('teacher.portal.lesson-notes.edit', $note->id) }}"
           class="mt-2 inline-block text-xs text-amber-700 font-medium hover:underline">Edit and re-submit →</a>
    </div>
    @endif

    {{-- Approval confirmation --}}
    @if($note->status === 'approved')
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <p class="text-sm text-green-800">
            ✓ Approved by <strong>{{ $note->approvedBy?->name ?? 'Admin' }}</strong>
            @if($note->approved_at) on {{ $note->approved_at->format('d M Y') }} @endif
        </p>
    </div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="text-xl font-bold text-gray-900">{{ $note->title }}</h2>
        <p class="text-sm text-gray-400 mb-4">{{ ucfirst(str_replace('_', ' ', $note->type)) }}</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div><p class="text-xs text-gray-400">Class</p><p class="font-medium">{{ $note->schoolClass?->name }}</p></div>
            <div><p class="text-xs text-gray-400">Subject</p><p class="font-medium">{{ $note->subject?->name }}</p></div>
            <div><p class="text-xs text-gray-400">Lesson Date</p><p class="font-medium">{{ $note->lesson_date?->format('d M Y') ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400">Day</p><p class="font-medium">{{ $note->day_of_week ?? '—' }}{{ $note->period ? " · P{$note->period}" : '' }}</p></div>
            <div><p class="text-xs text-gray-400">Week Ending</p><p class="font-medium">{{ $note->week_ending?->format('d M Y') ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400">Duration</p><p class="font-medium">{{ $note->duration ? $note->duration . ' min' : '—' }}</p></div>
            <div><p class="text-xs text-gray-400">Term</p><p class="font-medium">{{ $note->term?->term_name }}</p></div>
        </div>
    </div>

    {{-- Curriculum reference --}}
    @if($note->strand || $note->content_standard || $note->indicator)
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Curriculum Reference</h3>
        @if($note->strand)<div><p class="text-xs text-gray-400">Strand</p><p class="text-sm font-medium">{{ $note->strand->name }}@if($note->subStrand) › {{ $note->subStrand->name }}@endif</p></div>@endif
        @if($note->content_standard)<div><p class="text-xs text-gray-400">Content Standard</p><p class="text-sm">{{ $note->content_standard }}</p></div>@endif
        @if($note->indicator_code || $note->indicator)<div><p class="text-xs text-gray-400">Indicator @if($note->indicator_code)({{ $note->indicator_code }})@endif</p><p class="text-sm">{{ $note->indicator }}</p></div>@endif
        @if($note->performance_indicator)<div><p class="text-xs text-gray-400">Performance Indicator</p><p class="text-sm">{{ $note->performance_indicator }}</p></div>@endif
    </div>
    @endif

    {{-- Resources --}}
    @if($note->reference_materials || $note->tlr || ($note->core_competencies && count($note->coreCompetencyLabels())))
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Resources & Competencies</h3>
        @if($note->reference_materials)<div><p class="text-xs text-gray-400">Reference Materials</p><p class="text-sm">{{ $note->reference_materials }}</p></div>@endif
        @if($note->tlr)<div><p class="text-xs text-gray-400">TLR</p><p class="text-sm">{{ $note->tlr }}</p></div>@endif
        @if($note->keywords)<div><p class="text-xs text-gray-400">Keywords</p><p class="text-sm">{{ $note->keywords }}</p></div>@endif
        @if(count($note->coreCompetencyLabels()))
        <div>
            <p class="text-xs text-gray-400 mb-2">Core Competencies</p>
            <div class="flex flex-wrap gap-2">
                @foreach($note->coreCompetencyLabels() as $cc)
                    <span class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-full border border-blue-100">{{ $cc }}</span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- Lesson body --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Lesson Body</h3>
        @foreach([
            'starter'         => 'Starter / Introduction',
            'main_activities' => 'Main Activities',
            'assessment'      => 'Assessment',
            'conclusion'      => 'Conclusion / Closure',
            'homework'        => 'Homework',
        ] as $field => $label)
            @if($note->$field)
            <div class="border-l-2 border-blue-200 pl-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ $label }}</p>
                <p class="text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $note->$field }}</p>
            </div>
            @endif
        @endforeach
    </div>

    {{-- Attachments --}}
    @if($note->attachments->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Attachments</h3>
        <div class="space-y-2">
            @foreach($note->attachments as $att)
            <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50">
                <a href="{{ $att->publicUrl() }}" target="_blank" class="flex items-center gap-3 min-w-0">
                    <span class="text-xl">{{ $att->typeIcon() }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $att->original_name }}</p>
                        <p class="text-xs text-gray-400">{{ $att->humanSize() }}</p>
                    </div>
                </a>
                @if(in_array($note->status, ['draft', 'revision_requested']))
                <form method="POST"
                      action="{{ route('teacher.portal.lesson-notes.attachment.destroy', [$note->id, $att->id]) }}"
                      onsubmit="return confirm('Remove this attachment?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                </form>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Delete --}}
    @if(in_array($note->status, ['draft', 'revision_requested']))
    <div class="flex justify-end pb-4">
        <form method="POST" action="{{ route('teacher.portal.lesson-notes.destroy', $note->id) }}"
              onsubmit="return confirm('Delete this lesson note permanently?')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-red-500 hover:text-red-700">Delete note</button>
        </form>
    </div>
    @endif

</div>
@endsection

@push('head')
<style>
@media print {
    nav, .no-print { display: none !important; }
    body { background: white; }
    .bg-white { box-shadow: none; border: none; }
}
</style>
@endpush
