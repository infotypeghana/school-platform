@extends('layouts.admin')

@section('title', 'Edit Fee Record')
@section('page-title', 'Edit Fee Record')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="POST" action="{{ route('admin.fees.update', $fee) }}" class="space-y-4">
      @csrf @method('PUT')
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Student *</label>
        <select name="student_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          @foreach($students as $student)
            <option value="{{ $student->id }}" {{ old('student_id', $fee->student_id) == $student->id ? 'selected' : '' }}>
              {{ $student->full_name }} ({{ $student->schoolClass?->full_name }})
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term *</label>
        <select name="term_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          @foreach($terms as $term)
            <option value="{{ $term->id }}" {{ old('term_id', $fee->term_id) == $term->id ? 'selected' : '' }}>
              {{ $term->term_name }} · {{ $term->academicYear?->year_label }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fee Type *</label>
        <input type="text" name="fee_type" value="{{ old('fee_type', $fee->fee_type) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Due (GHS) *</label>
          <input type="number" name="amount" value="{{ old('amount', $fee->amount) }}" min="0.01" step="0.01" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount Paid (GHS)</label>
          <input type="number" name="amount_paid" value="{{ old('amount_paid', $fee->amount_paid) }}" min="0" step="0.01"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
          <input type="date" name="due_date" value="{{ old('due_date', $fee->due_date?->format('Y-m-d')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
          <input type="text" name="receipt_number" value="{{ old('receipt_number', $fee->receipt_number) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="flex flex-wrap gap-3 pt-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save Changes</button>
        <a href="{{ route('admin.fees') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
        @if($fee->amount_paid > 0)
          <a href="{{ route('admin.fees.receipt', $fee) }}" target="_blank"
             class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg border border-emerald-300 text-emerald-700 text-sm font-medium hover:bg-emerald-50 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Download Receipt
          </a>
        @endif
      </div>
    </form>
  </div>
</div>
@endsection
