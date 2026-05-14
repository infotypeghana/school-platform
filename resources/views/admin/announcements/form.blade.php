@extends('layouts.admin')

@section('title', isset($announcement) ? 'Edit Announcement' : 'New Announcement')
@section('page-title', isset($announcement) ? 'Edit Announcement' : 'New Announcement')

@section('content')
<div class="max-w-2xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    @php $action = isset($announcement)
        ? route('admin.announcements.update', $announcement)
        : route('admin.announcements.store');
    @endphp

    <form method="POST" action="{{ $action }}" class="space-y-4"
          x-data="{ audience: '{{ old('audience', $announcement?->audience ?? 'all') }}' }">
      @csrf
      @isset($announcement) @method('PUT') @endisset

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
        <input type="text" name="title"
               value="{{ old('title', $announcement?->title ?? '') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
               placeholder="e.g. School Closes Friday for Independence Day">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Body *</label>
        <textarea name="body" rows="5"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                  placeholder="Full announcement text…">{{ old('body', $announcement?->body ?? '') }}</textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Audience *</label>
          <select name="audience" x-model="audience"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="all">Everyone</option>
            <option value="teachers">Teachers only</option>
            <option value="parents">Parents only</option>
            <option value="class">Specific class</option>
          </select>
        </div>

        <div x-show="audience === 'class'" x-cloak>
          <label class="block text-sm font-medium text-gray-700 mb-1">Class *</label>
          <select name="school_class_id"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">— select —</option>
            @foreach($classes as $class)
              <option value="{{ $class->id }}"
                {{ old('school_class_id', $announcement?->school_class_id ?? '') == $class->id ? 'selected' : '' }}>
                {{ $class->full_name }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Publish Date</label>
          <input type="datetime-local" name="published_at"
                 value="{{ old('published_at', $announcement?->published_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <p class="text-xs text-gray-400 mt-1">Leave as now to publish immediately</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
          <input type="datetime-local" name="expires_at"
                 value="{{ old('expires_at', $announcement?->expires_at?->format('Y-m-d\TH:i') ?? '') }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <p class="text-xs text-gray-400 mt-1">Optional — auto-hides after this date</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <input type="hidden" name="is_pinned" value="0">
        <input type="checkbox" name="is_pinned" value="1" id="pinned"
               {{ old('is_pinned', $announcement?->is_pinned ?? false) ? 'checked' : '' }}
               class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
        <label for="pinned" class="text-sm font-medium text-gray-700">Pin to top of board</label>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
          {{ isset($announcement) ? 'Save Changes' : 'Publish' }}
        </button>
        <a href="{{ route('admin.announcements.index') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
