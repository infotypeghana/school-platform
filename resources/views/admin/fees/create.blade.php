@extends('layouts.admin')

@section('title', 'Record Fee')
@section('page-title', 'Record Fee')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="POST" action="{{ route('admin.fees.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Student *</label>
        <select name="student_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select student —</option>
          @foreach($students as $student)
            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
              {{ $student->full_name }} ({{ $student->schoolClass?->full_name }})
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term *</label>
        <select name="term_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select term —</option>
          @foreach($terms as $term)
            <option value="{{ $term->id }}" {{ old('term_id', $current?->id) == $term->id ? 'selected' : '' }}>
              {{ $term->term_name }} · {{ $term->academicYear?->year_label }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fee Type *</label>
        <input type="text" name="fee_type" value="{{ old('fee_type') }}"
               placeholder="e.g. Tuition, Feeding, PTA, Uniform"
               list="fee-types"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <datalist id="fee-types">
          @foreach(['Tuition', 'Feeding', 'PTA Levy', 'Uniform', 'Examination Fee', 'Sports', 'Library'] as $type)
            <option value="{{ $type }}">
          @endforeach
        </datalist>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Due (GHS) *</label>
          <input type="number" name="amount" value="{{ old('amount') }}" min="0.01" step="0.01" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Paid (GHS)</label>
          <input type="number" name="amount_paid" value="{{ old('amount_paid', 0) }}" min="0" step="0.01"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
          <input type="date" name="due_date" value="{{ old('due_date') }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
          <input type="text" name="receipt_number" value="{{ old('receipt_number') }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save Fee Record</button>
        <a href="{{ route('admin.fees') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
