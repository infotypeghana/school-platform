@extends('layouts.website')

@section('title', 'Gallery — ' . ($tenant?->name ?? 'School'))

@section('content')
@php $primaryColor = $tenant?->primary_color ?? '#1d4ed8'; @endphp

<section class="text-white py-20 text-center"
         style="background: linear-gradient(135deg, {{ $primaryColor }}dd 0%, {{ $primaryColor }} 100%);">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">Photo Gallery</h1>
    <p class="text-white/80">Moments from our school community at {{ $tenant?->name }}.</p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

  @if(!empty($galleryImages))
    {{-- Real uploaded photos --}}
    <div class="columns-2 sm:columns-3 lg:columns-4 gap-4 space-y-4">
      @foreach($galleryImages as $img)
        <div class="break-inside-avoid rounded-2xl overflow-hidden shadow-sm border border-gray-100">
          <img src="{{ asset('storage/' . $img) }}"
               alt="School photo"
               class="w-full h-auto object-cover hover:scale-105 transition-transform duration-300">
        </div>
      @endforeach
    </div>
  @else
    {{-- Placeholder --}}
    <div class="columns-2 sm:columns-3 lg:columns-4 gap-4 space-y-4">
      @php
        $heights = [180, 220, 160, 200, 240, 170, 190, 210, 155, 230, 175, 200];
      @endphp
      @for($i = 0; $i < 12; $i++)
        <div class="break-inside-avoid rounded-2xl overflow-hidden flex items-center justify-center"
             style="height:{{ $heights[$i] }}px; background: {{ $primaryColor }}{{ sprintf('%02x', 20 + ($i % 4) * 10) }};">
          <svg class="h-10 w-10 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
      @endfor
    </div>
    <div class="text-center mt-10">
      <p class="text-gray-400 text-sm mb-2">No photos have been uploaded yet.</p>
      <p class="text-xs text-gray-300">School administrators can upload gallery photos in
        <strong>Settings → Website</strong>.</p>
    </div>
  @endif

</section>
@endsection
