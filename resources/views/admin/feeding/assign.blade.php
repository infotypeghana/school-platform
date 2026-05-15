@extends('layouts.admin')

@section('title', 'Bulk Assign Feeding Fees')
@section('page-title', 'Feeding Fees')

@section('content')

{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  <a href="{{ route('admin.feeding.index') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Fees List</a>
  <a href="{{ route('admin.feeding.config') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Configuration</a>
  <a href="{{ route('admin.feeding.assign') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Bulk Assign</a>
  <a href="{{ route('admin.feeding.report') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Report</a>
</div>

<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-1">Bulk Assign Feeding Fees</h2>
    <p class="text-sm text-gray-500 mb-5">
      Assign feeding fees to <strong>all active students in a class</strong> for a term.
      Students who already have a fee for that term are automatically skipped.
    </p>

    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.feeding.assign.store') }}" class="space-y-4">
      @csrf

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
        <select name="class_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select class —</option>
          @foreach($classes as $class)
            <option value="{{ $class->id }}" {{ old('class_id') == $class->id ? 'selected' : '' }}>
              {{ $class->name }}
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
            <option value="{{ $term->id }}" {{ old('term_id', $terms->firstWhere('is_current', true)?->id) == $term->id ? 'selected' : '' }}>
              {{ $term->term_name }} {{ $term->academicYear?->year_label ? '(' . $term->academicYear->year_label . ')' : '' }}
              {{ $term->is_current ? '— Current' : '' }}
            </option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Feeding Days *</label>
        <input type="number" name="feeding_days" min="1" max="365" required
               value="{{ old('feeding_days', 65) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-400">
          Number of school days the student is expected to eat. The rate configured for
          the class (or school-wide) × feeding days = amount due.
        </p>
      </div>

      <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
        <strong>Note:</strong> Make sure you have set a feeding fee rate in
        <a href="{{ route('admin.feeding.config') }}" class="underline">Configuration</a>
        before assigning. If no rate is set, this will fail.
      </div>

      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
          Assign Feeding Fees
        </button>
        <a href="{{ route('admin.feeding.index') }}"
           class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>

@endsection
