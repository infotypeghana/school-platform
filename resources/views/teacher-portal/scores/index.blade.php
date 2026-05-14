@extends('layouts.teacher-portal')
@section('title', 'Score Entry')

@section('content')

<div class="mb-5">
    <h1 class="text-xl font-bold text-gray-900">Score Entry</h1>
    <p class="text-sm text-gray-500 mt-0.5">Select a subject and term to enter CA and exam scores.</p>
</div>

@if($subjects->isEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
        </div>
        <p class="text-gray-600 font-medium">No subjects assigned</p>
        <p class="text-sm text-gray-400 mt-1">Ask your administrator to assign subjects to you.</p>
    </div>
@else
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-lg">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Select Subject &amp; Term</h2>

        <form method="GET" action="{{ route('teacher.portal.scores.edit') }}" class="space-y-4">
            <div>
                <label for="subject_id" class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
                <select id="subject_id" name="subject_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select subject…</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" {{ old('subject_id', request('subject_id')) == $subject->id ? 'selected' : '' }}>
                            {{ $subject->name }}
                            @if($subject->schoolClass)
                                — {{ $subject->schoolClass->name }}{{ $subject->schoolClass->section ? " ({$subject->schoolClass->section})" : '' }}
                            @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="term_id" class="block text-xs font-medium text-gray-700 mb-1">Term</label>
                <select id="term_id" name="term_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select term…</option>
                    @foreach($terms as $term)
                        <option value="{{ $term->id }}"
                            {{ old('term_id', request('term_id', $current?->id)) == $term->id ? 'selected' : '' }}>
                            {{ $term->term_name }} — {{ $term->academicYear?->year_label }}
                            {{ $term->id === $current?->id ? '(current)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors">
                Load Score Grid →
            </button>
        </form>
    </div>
@endif

@endsection
