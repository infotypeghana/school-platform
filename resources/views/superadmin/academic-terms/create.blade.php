@extends('layouts.superadmin')

@section('title', 'Add Term')
@section('page-title', 'Add Academic Term')

@section('content')
<div class="max-w-md">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="POST" action="{{ route('superadmin.academic-terms.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Academic Year *</label>
        <select name="academic_year_id" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Select year —</option>
          @foreach($years as $year)
            <option value="{{ $year->id }}" {{ old('academic_year_id') == $year->id ? 'selected' : '' }}>
              {{ $year->year_label }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Term Number *</label>
          <select name="term_number" required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">—</option>
            @foreach([1,2,3] as $n)
              <option value="{{ $n }}" {{ old('term_number') == $n ? 'selected' : '' }}>Term {{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Term Name *</label>
          <input type="text" name="term_name" value="{{ old('term_name') }}" required
                 placeholder="e.g. First Term"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
          <input type="date" name="start_date" value="{{ old('start_date') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
          <input type="date" name="end_date" value="{{ old('end_date') }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_current" id="is_current" value="1"
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="is_current" class="text-sm text-gray-700">Set as current term</label>
      </div>
      <p class="text-xs text-gray-400">Grace period = End Date + 5 days (automatic per billing config).</p>
      <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Create Term</button>
        <a href="{{ route('superadmin.academic-terms.index') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
