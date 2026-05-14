@extends('layouts.admin')

@section('title', 'Announcements')
@section('page-title', 'Announcements & Bulletin Board')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <form method="GET" class="flex gap-2">
    <select name="audience" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All audiences</option>
      @foreach(['all' => 'Everyone', 'teachers' => 'Teachers', 'parents' => 'Parents', 'class' => 'Class'] as $val => $label)
        <option value="{{ $val }}" {{ request('audience') === $val ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <a href="{{ route('admin.announcements.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
    + New Announcement
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="space-y-3">
  @forelse($announcements as $announcement)
    <div class="bg-white rounded-xl border {{ $announcement->is_pinned ? 'border-amber-200' : 'border-gray-100' }} shadow-sm p-5">
      <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            @if($announcement->is_pinned)
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                Pinned
              </span>
            @endif
            @php
              $audienceColor = match($announcement->audience) {
                'all'      => 'bg-blue-100 text-blue-700',
                'teachers' => 'bg-purple-100 text-purple-700',
                'parents'  => 'bg-teal-100 text-teal-700',
                'class'    => 'bg-indigo-100 text-indigo-700',
                default    => 'bg-gray-100 text-gray-600',
              };
            @endphp
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $audienceColor }}">
              {{ $announcement->audience_label }}
            </span>
            @if(! $announcement->is_active)
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
            @endif
          </div>
          <h3 class="text-base font-semibold text-gray-900">{{ $announcement->title }}</h3>
          <p class="text-sm text-gray-600 mt-1 line-clamp-2">{{ $announcement->body }}</p>
          <p class="text-xs text-gray-400 mt-2">
            By {{ $announcement->author?->name ?? '—' }} ·
            {{ $announcement->published_at?->format('d M Y, g:i a') ?? 'Draft' }}
            @if($announcement->expires_at)
              · Expires {{ $announcement->expires_at->format('d M Y') }}
            @endif
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <a href="{{ route('admin.announcements.edit', $announcement) }}"
             class="text-xs text-blue-600 hover:underline">Edit</a>
          <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}"
                onsubmit="return confirm('Delete this announcement?')" class="inline">
            @csrf @method('DELETE')
            <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
          </form>
        </div>
      </div>
    </div>
  @empty
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-16 text-center">
      <p class="text-gray-400 text-sm">No announcements yet. Create one to notify parents and staff.</p>
      <a href="{{ route('admin.announcements.create') }}"
         class="mt-4 inline-block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
        + New Announcement
      </a>
    </div>
  @endforelse
</div>

@if($announcements->hasPages())
  <div class="mt-4">{{ $announcements->links() }}</div>
@endif

@endsection
