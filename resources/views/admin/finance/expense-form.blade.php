@extends('layouts.admin')

@section('title', $expense ? 'Edit Expense' : 'Record Expense')
@section('page-title', $expense ? 'Edit Expense' : 'Record Expense')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    @php
      $action = $expense
        ? route('admin.finance.expenses.update', $expense)
        : route('admin.finance.expenses.store');
    @endphp

    <form method="POST" action="{{ $action }}" class="space-y-4">
      @csrf
      @if($expense) @method('PUT') @endif

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
        <input type="text" name="description" value="{{ old('description', $expense?->description) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. January electricity bill">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
          <select name="category" required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            @foreach(\App\Models\Expense::CATEGORIES as $val => $label)
              <option value="{{ $val }}" {{ old('category', $expense?->category) === $val ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Amount (GHS) *</label>
          <input type="number" name="amount" value="{{ old('amount', $expense?->amount) }}"
                 min="0.01" step="0.01" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
          <input type="date" name="date" value="{{ old('date', $expense?->date?->format('Y-m-d') ?? today()->format('Y-m-d')) }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reference / Receipt No.</label>
          <input type="text" name="reference" value="{{ old('reference', $expense?->reference) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                 placeholder="Optional">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="2"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                  placeholder="Optional notes">{{ old('notes', $expense?->notes) }}</textarea>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
          {{ $expense ? 'Save Changes' : 'Record Expense' }}
        </button>
        <a href="{{ route('admin.finance.index') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
