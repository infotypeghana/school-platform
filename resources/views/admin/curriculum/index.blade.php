@extends('layouts.admin')

@section('title', 'Curriculum Management')
@section('page-title', 'Curriculum Management')

@section('content')
<div class="space-y-6">

    {{-- Add strand --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6" x-data="{ showForm: false }">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-800">Strands</h2>
            <button @click="showForm = !showForm"
                    class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium">
                + Add Strand
            </button>
        </div>

        {{-- New strand form --}}
        <div x-show="showForm" x-transition class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
            <form method="POST" action="{{ route('admin.curriculum.strands.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Subject *</label>
                        <select name="subject_id" required class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            <option value="">Select subject…</option>
                            @foreach($subjects as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Strand Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Number & Numeration"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Order</label>
                        <input type="number" name="order_index" value="0" min="0"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                        <input type="text" name="description" placeholder="Optional description"
                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <button type="submit" class="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700">
                    Save Strand
                </button>
            </form>
        </div>

        {{-- Strands list --}}
        @if($strands->isEmpty())
            <p class="text-sm text-gray-400 text-center py-6">No strands defined yet. Add a strand to get started.</p>
        @else
        <div class="space-y-4">
            @foreach($strands->groupBy('subject_id') as $subjectId => $subjectStrands)
                @php $firstStrand = $subjectStrands->first(); @endphp
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            {{ $firstStrand->subject?->name ?? 'Unknown Subject' }}
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($subjectStrands as $strand)
                        <div x-data="{ editStrand: false, editSub: false }" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-800 text-sm">{{ $strand->name }}</p>
                                    @if($strand->description)
                                        <p class="text-xs text-gray-400 mt-0.5">{{ $strand->description }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ $strand->subStrands->count() }} sub-strand(s)</p>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button @click="editSub = !editSub"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                        + Sub-strand
                                    </button>
                                    <button @click="editStrand = !editStrand"
                                            class="text-xs text-gray-500 hover:text-gray-700">Edit</button>
                                    <form method="POST" action="{{ route('admin.curriculum.strands.destroy', $strand->id) }}"
                                          onsubmit="return confirm('Delete this strand?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Edit strand form --}}
                            <div x-show="editStrand" x-transition class="mt-3 bg-gray-50 rounded-lg p-3 border border-gray-200">
                                <form method="POST" action="{{ route('admin.curriculum.strands.update', $strand->id) }}" class="space-y-2">
                                    @csrf @method('PUT')
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="text" name="name" value="{{ $strand->name }}" required
                                               class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                                        <input type="number" name="order_index" value="{{ $strand->order_index }}" min="0"
                                               class="text-sm border border-gray-300 rounded-lg px-3 py-2">
                                        <input type="hidden" name="subject_id" value="{{ $strand->subject_id }}">
                                        <input type="text" name="description" value="{{ $strand->description }}"
                                               placeholder="Description"
                                               class="text-sm border border-gray-300 rounded-lg px-3 py-2 col-span-2">
                                    </div>
                                    <button type="submit" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">
                                        Update
                                    </button>
                                </form>
                            </div>

                            {{-- Sub-strands --}}
                            @if($strand->subStrands->isNotEmpty())
                            <div class="mt-3 ml-4 space-y-2">
                                @foreach($strand->subStrands as $sub)
                                <div x-data="{ editSub{{ $sub->id }}: false }" class="flex items-start justify-between gap-3 text-sm">
                                    <div>
                                        <p class="text-gray-700">{{ $sub->name }}</p>
                                        @if($sub->description)
                                            <p class="text-xs text-gray-400">{{ $sub->description }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <button @click="editSub{{ $sub->id }} = !editSub{{ $sub->id }}"
                                                class="text-xs text-gray-500 hover:text-gray-700">Edit</button>
                                        <form method="POST" action="{{ route('admin.curriculum.sub-strands.destroy', $sub->id) }}"
                                              onsubmit="return confirm('Delete sub-strand?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div x-show="editSub{{ $sub->id }}" x-transition class="ml-0 bg-gray-50 rounded p-2 border border-gray-200">
                                    <form method="POST" action="{{ route('admin.curriculum.sub-strands.update', $sub->id) }}" class="flex gap-2">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="strand_id" value="{{ $strand->id }}">
                                        <input type="text" name="name" value="{{ $sub->name }}" required
                                               class="flex-1 text-sm border border-gray-300 rounded px-2 py-1">
                                        <input type="number" name="order_index" value="{{ $sub->order_index }}" min="0"
                                               class="w-16 text-sm border border-gray-300 rounded px-2 py-1">
                                        <button type="submit" class="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                            Save
                                        </button>
                                    </form>
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Add sub-strand form --}}
                            <div x-show="editSub" x-transition class="mt-3 ml-4 bg-indigo-50 rounded-lg p-3 border border-indigo-100">
                                <form method="POST" action="{{ route('admin.curriculum.sub-strands.store') }}" class="flex gap-2 items-end">
                                    @csrf
                                    <input type="hidden" name="strand_id" value="{{ $strand->id }}">
                                    <div class="flex-1">
                                        <label class="block text-xs text-gray-500 mb-1">Sub-strand Name *</label>
                                        <input type="text" name="name" required placeholder="e.g. Counting & Estimation"
                                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                                    </div>
                                    <div class="w-20">
                                        <label class="block text-xs text-gray-500 mb-1">Order</label>
                                        <input type="number" name="order_index" value="0" min="0"
                                               class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                                    </div>
                                    <button type="submit"
                                            class="text-xs bg-indigo-600 text-white px-3 py-2 rounded-lg hover:bg-indigo-700">
                                        Add
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
@endsection
