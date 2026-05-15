@extends('layouts.teacher-portal')

@section('title', 'New Lesson Note')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto" x-data="lessonNoteForm()">

    <div class="flex items-center justify-between">
        <a href="{{ route('teacher.portal.lesson-notes.index') }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Back</a>
        <h2 class="text-lg font-bold text-gray-900">New Lesson Note</h2>
        <div></div>
    </div>

    <form method="POST" action="{{ route('teacher.portal.lesson-notes.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- Core info --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Basic Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="e.g. Addition of Fractions — Week 3"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                    <select name="type" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="lesson_note" @selected(old('type','lesson_note')==='lesson_note')>Lesson Note</option>
                        <option value="lesson_plan" @selected(old('type')==='lesson_plan')>Lesson Plan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term *</label>
                    <select name="term_id" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected(old('term_id', $current?->id) == $t->id)>
                                {{ $t->term_name }} — {{ $t->academicYear?->year_label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Class *</label>
                    <select name="school_class_id" required x-model="selectedClassId" @change="filterSubjects()"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select class…</option>
                        @foreach($classes as $c)
                            <option value="{{ $c->id }}" @selected(old('school_class_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Subject *</label>
                    <select name="subject_id" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select subject…</option>
                        @foreach($subjects as $s)
                            <option value="{{ $s->id }}"
                                    data-class="{{ $s->school_class_id }}"
                                    @selected(old('subject_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Schedule --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Schedule</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Lesson Date</label>
                    <input type="date" name="lesson_date" value="{{ old('lesson_date') }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Week Ending</label>
                    <input type="date" name="week_ending" value="{{ old('week_ending') }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Day of Week</label>
                    <select name="day_of_week" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">—</option>
                        @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d)
                            <option value="{{ $d }}" @selected(old('day_of_week')===$d)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Period</label>
                    <input type="text" name="period" value="{{ old('period') }}" placeholder="e.g. 3rd"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Duration (mins)</label>
                    <input type="number" name="duration" value="{{ old('duration', 40) }}" min="1" max="480"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
        </div>

        {{-- GES Curriculum reference --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">GES Curriculum Reference</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Strand</label>
                    <select name="strand_id" x-model="selectedStrandId" @change="filterSubStrands()"
                            class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select strand…</option>
                        @foreach($strands as $strand)
                            <option value="{{ $strand->id }}" @selected(old('strand_id') == $strand->id)>{{ $strand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sub-Strand</label>
                    <select name="sub_strand_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">Select sub-strand…</option>
                        @foreach($subStrands as $sub)
                            <option value="{{ $sub->id }}"
                                    data-strand="{{ $sub->strand_id }}"
                                    @selected(old('sub_strand_id') == $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Content Standard</label>
                    <input type="text" name="content_standard" value="{{ old('content_standard') }}"
                           placeholder="e.g. Demonstrate understanding of fractions as part of a whole"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Indicator Code</label>
                    <input type="text" name="indicator_code" value="{{ old('indicator_code') }}" placeholder="e.g. B4.1.2.1"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Keywords</label>
                    <input type="text" name="keywords" value="{{ old('keywords') }}" placeholder="Comma-separated keywords"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Indicator</label>
                    <textarea name="indicator" rows="2" placeholder="The specific indicator for this lesson"
                              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('indicator') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Performance Indicator</label>
                    <textarea name="performance_indicator" rows="2" placeholder="How learner performance will be assessed"
                              class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('performance_indicator') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Resources --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Resources & Competencies</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reference Materials</label>
                    <input type="text" name="reference_materials" value="{{ old('reference_materials') }}"
                           placeholder="Textbook, curriculum doc, page numbers…"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Teaching & Learning Resources (TLR)</label>
                    <input type="text" name="tlr" value="{{ old('tlr') }}"
                           placeholder="e.g. Charts, counters, rulers, protractors"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-2">Core Competencies</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @foreach(\App\Models\LessonNote::CORE_COMPETENCIES as $key => $label)
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="core_competencies[]" value="{{ $key }}"
                                   @checked(in_array($key, old('core_competencies', [])))
                                   class="rounded border-gray-300 text-blue-600">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Lesson body --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Lesson Body</h3>
            @foreach([
                ['starter',          'Starter / Introduction', 'What will you do to introduce the lesson? (Review prior knowledge, warm-up activity)', false],
                ['main_activities',  'Main Activities *',      'Step-by-step description of teaching and learning activities', true],
                ['assessment',       'Assessment',             'How will you assess learners during and after the lesson?', false],
                ['conclusion',       'Conclusion / Closure',   'How will you close the lesson? Summary, questions', false],
                ['homework',         'Homework / Follow-up',   'Optional homework or extension activity', false],
            ] as [$field, $lbl, $ph, $req])
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $lbl }}</label>
                <textarea name="{{ $field }}" rows="4" placeholder="{{ $ph }}"
                          @if($req) required @endif
                          class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none">{{ old($field) }}</textarea>
            </div>
            @endforeach
        </div>

        {{-- Attachments --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Attachments (Optional)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([
                    ['attach_pdf',   'PDF Document',    'application/pdf'],
                    ['attach_word',  'Word Document',   '.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    ['attach_image', 'Image',           'image/*'],
                    ['attach_audio', 'Audio',           'audio/*'],
                    ['attach_video', 'Video',           'video/*'],
                ] as [$field, $lbl, $accept])
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $lbl }}</label>
                    <input type="file" name="{{ $field }}" accept="{{ $accept }}"
                           class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs file:font-medium hover:file:bg-blue-100">
                </div>
                @endforeach
                <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">External Link URL</label>
                        <input type="url" name="attach_link" placeholder="https://…"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Link Name</label>
                        <input type="text" name="attach_link_name" placeholder="e.g. YouTube video"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between gap-4 pb-6">
            <a href="{{ route('teacher.portal.lesson-notes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                Cancel
            </a>
            <div class="flex items-center gap-3">
                <button type="submit" name="action" value="draft"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    Save Draft
                </button>
                <button type="submit" name="action" value="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors"
                        onclick="return confirm('Submit this lesson note for approval?')">
                    Submit for Approval
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function lessonNoteForm() {
    return {
        selectedClassId: '{{ old('school_class_id', '') }}',
        selectedStrandId: '{{ old('strand_id', '') }}',
        filterSubjects() {
            document.querySelectorAll('select[name="subject_id"] option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (this.selectedClassId && opt.dataset.class !== this.selectedClassId) ? 'none' : '';
            });
        },
        filterSubStrands() {
            document.querySelectorAll('select[name="sub_strand_id"] option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (this.selectedStrandId && opt.dataset.strand !== this.selectedStrandId) ? 'none' : '';
            });
        },
    };
}
</script>
@endpush
