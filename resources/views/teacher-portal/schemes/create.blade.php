@extends('layouts.teacher-portal')

@section('title', 'New Scheme of Work')

@section('content')
<div class="space-y-6 max-w-3xl mx-auto" x-data="schemeForm()">

    <div class="flex items-center justify-between">
        <a href="{{ route('teacher.portal.schemes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back</a>
        <h2 class="text-lg font-bold text-gray-900">New Scheme of Work</h2>
        <div></div>
    </div>

    <form method="POST" action="{{ route('teacher.portal.schemes.store') }}" class="space-y-5">
        @csrf

        {{-- Header --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Title *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           placeholder="e.g. Mathematics Scheme of Work — Term 1 2025/2026"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-400 focus:outline-none">
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
                            <option value="{{ $s->id }}" data-class="{{ $s->school_class_id }}"
                                    @selected(old('subject_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"
                              placeholder="Optional brief description">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Weekly rows --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5" x-data="weekRows()">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Weekly Plan</h3>
                <button type="button" @click="addRow()"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add Week</button>
            </div>

            <div class="space-y-3" x-ref="rows">
                <template x-for="(row, i) in rows" :key="row.key">
                    <div class="border border-gray-200 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-gray-500" x-text="'Week ' + row.week_number"></span>
                            <button type="button" @click="removeRow(i)"
                                    class="text-xs text-red-500 hover:text-red-700" x-show="rows.length > 1">Remove</button>
                        </div>
                        <input type="hidden" :name="`weeks[${i}][week_number]`" :value="row.week_number">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Week Ending Date</label>
                                <input type="date" :name="`weeks[${i}][week_ending]`" x-model="row.week_ending"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Topic *</label>
                                <input type="text" :name="`weeks[${i}][topic]`" x-model="row.topic" required
                                       placeholder="e.g. Addition of whole numbers"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-gray-500 mb-1">Learning Objectives</label>
                                <textarea :name="`weeks[${i}][learning_objectives]`" x-model="row.objectives" rows="2"
                                          placeholder="What learners will be able to do by end of week"
                                          class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Competencies</label>
                                <input type="text" :name="`weeks[${i}][competencies]`" x-model="row.competencies"
                                       placeholder="e.g. Critical Thinking"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Reference</label>
                                <input type="text" :name="`weeks[${i}][reference]`" x-model="row.reference"
                                       placeholder="Textbook page, curriculum"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="addRow()"
                    class="mt-4 w-full border-2 border-dashed border-gray-200 text-sm text-gray-400 hover:text-gray-600 hover:border-gray-300 py-3 rounded-lg transition-colors">
                + Add Another Week
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pb-6">
            <a href="{{ route('teacher.portal.schemes.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-6 py-2 rounded-lg">
                Save Scheme
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function schemeForm() {
    return {
        selectedClassId: '{{ old('school_class_id', '') }}',
        filterSubjects() {
            document.querySelectorAll('select[name="subject_id"] option').forEach(opt => {
                if (!opt.value) { opt.style.display = ''; return; }
                opt.style.display = (this.selectedClassId && opt.dataset.class !== this.selectedClassId) ? 'none' : '';
            });
        }
    };
}
function weekRows() {
    return {
        nextKey: 2,
        rows: [{key:1, week_number:1, week_ending:'', topic:'', objectives:'', competencies:'', reference:''}],
        addRow() {
            this.rows.push({key:this.nextKey++, week_number:this.rows.length+1, week_ending:'', topic:'', objectives:'', competencies:'', reference:''});
        },
        removeRow(i) {
            this.rows.splice(i,1);
            this.rows.forEach((r,idx) => r.week_number = idx+1);
        }
    };
}
</script>
@endpush
