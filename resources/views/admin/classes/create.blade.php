@extends('layouts.admin')

@section('title', 'Add Class')
@section('page-title', 'Add Class')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="POST" action="{{ route('admin.classes.store') }}" class="space-y-4">
      @csrf
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class Name *</label>
        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. JHS 1, Basic 6, Form 3"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Level / Stage</label>
          <input type="text" name="level" value="{{ old('level') }}" placeholder="e.g. JHS 1"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Section (optional)</label>
          <input type="text" name="section" value="{{ old('section') }}" placeholder="e.g. A, Blue, Gold"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Class Teacher</label>
        <select name="class_teacher_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— Assign later —</option>
          @foreach($teachers as $t)
            <option value="{{ $t->id }}" {{ old('class_teacher_id') == $t->id ? 'selected' : '' }}>{{ $t->full_name }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Subjects (one per line)</label>
        <textarea name="subjects" rows="5" placeholder="Mathematics&#10;English Language&#10;Science&#10;Social Studies&#10;Ghanaian Language"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 font-mono">{{ old('subjects') }}</textarea>
        <p class="text-xs text-gray-400 mt-1">You can also add subjects later from the class detail page.</p>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Create Class</button>
        <a href="{{ route('admin.classes') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
