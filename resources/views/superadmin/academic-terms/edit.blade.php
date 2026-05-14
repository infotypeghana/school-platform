@extends('layouts.superadmin')

@section('title', 'Edit Term')
@section('page-title', 'Edit Academic Term')

@section('content')
<div class="max-w-md">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if(session('success'))
      <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <p class="text-xs text-gray-400 mb-4">Academic Year: <strong>{{ $term->academicYear?->year_label }}</strong> · Term {{ $term->term_number }}</p>

    <form method="POST" action="{{ route('superadmin.academic-terms.update', $term) }}" class="space-y-4">
      @csrf @method('PUT')
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term Name *</label>
        <input type="text" name="term_name" value="{{ old('term_name', $term->term_name) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
          <input type="date" name="start_date" value="{{ old('start_date', $term->start_date->format('Y-m-d')) }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
          <input type="date" name="end_date" value="{{ old('end_date', $term->end_date->format('Y-m-d')) }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_current" id="is_current" value="1"
               {{ $term->is_current ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="is_current" class="text-sm text-gray-700">Set as current term</label>
      </div>
      <div class="flex gap-3">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save</button>
        <a href="{{ route('superadmin.academic-terms.index') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
