@extends('layouts.admin')

@section('title', 'Bulk Assign Fees')
@section('page-title', 'Bulk Assign Fees')

@section('content')
<div class="max-w-xl">

  <div class="mb-5 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 text-sm">
    <strong>How this works:</strong> Choose a class, a term, and a fee type — a fee record will be created for
    every <strong>active</strong> student in that class. Students who already have a record for the same term
    and fee type are skipped automatically.
  </div>

  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

    @if($errors->any())
      <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.fees.bulk.store') }}" class="space-y-4">
      @csrf

      {{-- Class --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
        <select name="school_class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}" {{ old('school_class_id') == $class->id ? 'selected' : '' }}>
              {{ $class->full_name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Term --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term *</label>
        <select name="term_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select term —</option>
          @foreach($terms as $term)
            <option value="{{ $term->id }}"
                    {{ old('term_id', $current?->id) == $term->id ? 'selected' : '' }}>
              {{ $term->term_name }} — {{ $term->academicYear?->year_label }}
              @if($term->is_current) (Current) @endif
            </option>
          @endforeach
        </select>
      </div>

      {{-- Fee Type --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Fee Type *</label>
        <input type="text" name="fee_type" value="{{ old('fee_type') }}" required
               list="fee-type-suggestions"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. Tuition, Feeding, PTA Levy">
        <datalist id="fee-type-suggestions">
          @foreach(['Tuition', 'Feeding', 'PTA Levy', 'Uniform', 'Examination Fee', 'Sports', 'Library'] as $type)
            <option value="{{ $type }}">
          @endforeach
        </datalist>
      </div>

      {{-- Amount --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (GHS) *</label>
        <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. 500.00">
      </div>

      {{-- Amount Paid (optional) --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount Already Paid (GHS)</label>
        <input type="number" name="amount_paid" value="{{ old('amount_paid', 0) }}" step="0.01" min="0"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-400">Leave as 0 if no payment has been collected yet.</p>
      </div>

      {{-- Due Date --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
        <input type="date" name="due_date" value="{{ old('due_date') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>

      {{-- Actions --}}
      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Assign Fees to Class
        </button>
        <a href="{{ route('admin.fees') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
