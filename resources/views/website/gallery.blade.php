@extends('layouts.website')

@section('title', 'Gallery — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">Photo Gallery</h1>
    <p class="text-blue-100">Moments from our school community at {{ $tenant?->name }}.</p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
  {{-- Placeholder gallery grid --}}
  <div class="columns-2 sm:columns-3 lg:columns-4 gap-4 space-y-4">
    @php
      $heights = [180, 220, 160, 200, 240, 170, 190, 210, 155, 230, 175, 200];
      $colors  = ['from-blue-200 to-blue-100','from-violet-200 to-violet-100','from-emerald-200 to-emerald-100','from-amber-200 to-amber-100','from-rose-200 to-rose-100','from-indigo-200 to-indigo-100'];
    @endphp
    @for($i = 0; $i < 12; $i++)
      <div class="break-inside-avoid rounded-2xl overflow-hidden bg-gradient-to-br {{ $colors[$i % count($colors)] }} flex items-center justify-center"
           style="height: {{ $heights[$i] }}px;">
        <svg class="h-10 w-10 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
    @endfor
  </div>

  <p class="text-center text-gray-400 text-sm mt-10">
    Photos from our school events, sports days, and academic activities will appear here.
  </p>
</section>
@endsection
