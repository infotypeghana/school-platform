@extends('layouts.teacher-portal')

@section('title', 'Edit Lesson Note')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto" x-data="lessonNoteForm()">

    <div class="flex items-center justify-between">
        <a href="{{ route('teacher.portal.lesson-notes.show', $note->id) }}"
           class="text-sm text-gray-500 hover:text-gray-700">← Back</a>
        <h2 class="text-lg font-bold text-gray-900">Edit Lesson Note</h2>
        <div></div>
    </div>

    @if($note->status === 'revision_requested' && $note->revision_notes)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-xs font-semibold text-amber-700 mb-1">REVISION REQUESTED</p>
        <p class="text-sm text-amber-900">{{ $note->revision_notes }}</p>
    </div>
    @endif

    <form method="POST" action="{{ route('teacher.portal.lesson-notes.update', $note->id) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf @method('PUT')

        {{-- Core info --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Basic Information</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                    <input type="text" name="title" value="{{ old('title', $note->title) }}" required
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type *</label>
                    <select name="type" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="lesson_note" @selected(old('type',$note->type)==='lesson_note')>Lesson Note</option>
                        <option value="lesson_plan" @selected(old('type',$note->type)==='lesson_plan')>Lesson Plan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term *</label>
                    <select name="term_id" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected(old('term_id',$note->term_id) == $t->id)>
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
                            <option value="{{ $c->id }}" @selected(old('school_class_id',$note->school_class_id) == $c->id)>{{ $c->name }}</option>
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
                                    @selected(old('subject_id',$note->subject_id) == $s->id)>{{ $s->name }}</option>
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
                    <input type="date" name="lesson_date" value="{{ old('lesson_date', $note->lesson_date?->format('Y-m-d')) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Week Ending</label>
                    <input type="date" name="week_ending" value="{{ old('week_ending', $note->week_ending?->format('Y-m-d')) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Day of Week</label>
                    <select name="day_of_week" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">—</option>
                        @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d)
                            <option value="{{ $d }}" @selected(old('day_of_week',$note->day_of_week)===$d)>{{ $d }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Period</label>
                    <input type="text" name="period" value="{{ old('period', $note->period) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Duration (mins)</label>
                    <input type="number" name="duration" value="{{ old('duration', $note->duration ?? 40) }}" min="1" max="480"
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
                        <option value="">—</option>
                        @foreach($strands as $strand)
                            <option value="{{ $strand->id }}" @selected(old('strand_id',$note->strand_id) == $strand->id)>{{ $strand->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sub-Strand</label>
                    <select name="sub_strand_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">—</option>
                        @foreach($subStrands as $sub)
                            <option value="{{ $sub->id }}" data-strand="{{ $sub->strand_id }}"
                                    @selected(old('sub_strand_id',$note->sub_strand_id) == $sub->id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Content Standard</label>
                    <input type="text" name="content_standard" value="{{ old('content_standard', $note->content_standard) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Indicator Code</label>
                    <input type="text" name="indicator_code" value="{{ old('indicator_code', $note->indicator_code) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 font-mono">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Keywords</label>
                    <input type="text" name="keywords" value="{{ old('keywords', $note->keywords) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Indicator</label>
                    <textarea name="indicator" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('indicator', $note->indicator) }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Performance Indicator</label>
                    <textarea name="performance_indicator" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">{{ old('performance_indicator', $note->performance_indicator) }}</textarea>
                </div>
            </div>
        </div>

        {{-- Resources --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Resources & Competencies</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reference Materials</label>
                    <input type="text" name="reference_materials" value="{{ old('reference_materials', $note->reference_materials) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Teaching & Learning Resources (TLR)</label>
                    <input type="text" name="tlr" value="{{ old('tlr', $note->tlr) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-2">Core Competencies</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        @php $oldCC = old('core_competencies', $note->core_competencies ?? []); @endphp
                        @foreach(\App\Models\LessonNote::CORE_COMPETENCIES as $key => $label)
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" name="core_competencies[]" value="{{ $key }}"
                                   @checked(in_array($key, $oldCC))
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
                ['starter',          'Starter / Introduction', false],
                ['main_activities',  'Main Activities *',      true],
                ['assessment',       'Assessment',             false],
                ['conclusion',       'Conclusion / Closure',   false],
                ['homework',         'Homework / Follow-up',   false],
            ] as [$field, $lbl, $req])
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $lbl }}</label>
                <textarea name="{{ $field }}" rows="4" @if($req) required @endif
                          class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none">{{ old($field, $note->$field) }}</textarea>
            </div>
            @endforeach
        </div>

        {{-- Add more attachments --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Add Attachments</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach([['attach_pdf','PDF','application/pdf'],['attach_word','Word','.doc,.docx'],['attach_image','Image','image/*'],['attach_audio','Audio','audio/*'],['attach_video','Video','video/*']] as [$f,$l,$a])
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $l }}</label>
                    <input type="file" name="{{ $f }}" accept="{{ $a }}"
                           class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs hover:file:bg-blue-100">
                </div>
                @endforeach
                <div class="sm:col-span-2 grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">External Link URL</label>
                    <input type="url" name="attach_link" placeholder="https://…" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"></div>
                    <div><label class="block text-xs font-medium text-gray-600 mb-1">Link Name</label>
                    <input type="text" name="attach_link_name" placeholder="e.g. YouTube video" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"></div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pb-6">
            <a href="{{ route('teacher.portal.lesson-notes.show', $note->id) }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            <div class="flex items-center gap-3">
                <button type="submit" name="action" value="draft"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm font-medium px-5 py-2 rounded-lg">
                    Save Draft
                </button>
                <button type="submit" name="action" value="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg"
                        onclick="return confirm('Submit for approval?')">
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
        selectedClassId: '{{ old('school_class_id', $note->school_class_id) }}',
        selectedStrandId: '{{ old('strand_id', $note->strand_id) }}',
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
