@extends('layouts.website')

@section('title', 'News — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">School News & Updates</h1>
    <p class="text-blue-100">Stay informed with the latest happenings at {{ $tenant?->name }}.</p>
  </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @for($i = 0; $i < 9; $i++)
      <article class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
        <div class="h-44 bg-gradient-to-br from-blue-100 to-indigo-50 flex items-center justify-center">
          <svg class="h-12 w-12 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
          </svg>
        </div>
        <div class="p-5">
          <p class="text-xs text-gray-400 mb-1">{{ now()->subDays($i * 4)->format('d M Y') }}</p>
          <h3 class="font-semibold text-gray-900 mb-2">School Update #{{ $i + 1 }}</h3>
          <p class="text-sm text-gray-500 line-clamp-3">
            Stay connected with our school community. Regular updates and announcements will be posted here
            to keep parents and guardians informed of important events and activities.
          </p>
          <span class="inline-block mt-3 text-xs text-blue-600 font-medium hover:underline cursor-pointer">Read more →</span>
        </div>
      </article>
    @endfor
  </div>
</section>
@endsection
