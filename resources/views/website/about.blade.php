@extends('layouts.website')

@section('title', 'About Us — ' . ($tenant?->name ?? 'School'))

@section('content')
@php
    $primaryColor = $tenant?->primary_color ?? '#1d4ed8';
    $mission      = $wc['mission']     ?? 'To provide quality, holistic education aligned with the Ghana Education Service curriculum, nurturing every student\'s academic, moral, and social development to achieve their fullest potential.';
    $vision       = $wc['vision']      ?? 'To be the premier institution of choice in our community — producing well-rounded graduates who are equipped to excel in national examinations and contribute positively to society.';
    $aboutIntro   = $wc['about_intro'] ?? '';
@endphp

{{-- Hero --}}
<section class="text-white py-20 text-center"
         style="background: linear-gradient(135deg, {{ $primaryColor }}dd 0%, {{ $primaryColor }} 100%);">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">About {{ $tenant?->name }}</h1>
    <p class="text-white/80 text-lg">Excellence in education. Shaping the future of Ghana, one student at a time.</p>
  </div>
</section>

{{-- About intro paragraph --}}
@if($aboutIntro)
<section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-14 pb-4">
  <p class="text-gray-600 leading-relaxed text-base">{{ $aboutIntro }}</p>
</section>
@endif

{{-- Mission & Vision --}}
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
    <div class="rounded-2xl p-8" style="background: {{ $primaryColor }}11; border: 1px solid {{ $primaryColor }}33;">
      <div class="text-3xl mb-3">🎯</div>
      <h2 class="text-xl font-bold mb-3" style="color: {{ $primaryColor }}">Our Mission</h2>
      <p class="text-gray-600 leading-relaxed">{{ $mission }}</p>
    </div>
    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-8">
      <div class="text-3xl mb-3">🌟</div>
      <h2 class="text-xl font-bold text-emerald-900 mb-3">Our Vision</h2>
      <p class="text-gray-600 leading-relaxed">{{ $vision }}</p>
    </div>
  </div>
</section>

{{-- Core values --}}
<section class="bg-gray-50 py-16">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">Our Core Values</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
      @foreach([
        ['🏅','Excellence',     'We strive for the highest standards in all we do.'],
        ['🤝','Integrity',      'Honesty and transparency in every interaction.'],
        ['🌱','Growth',         'Continuous improvement for students and staff alike.'],
        ['🤗','Inclusiveness',  'Every child deserves quality education.'],
      ] as [$icon, $title, $desc])
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="text-3xl mb-2">{{ $icon }}</div>
          <h3 class="font-semibold text-gray-900 mb-1">{{ $title }}</h3>
          <p class="text-xs text-gray-500">{{ $desc }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- Contact strip --}}
<section class="py-12 text-center">
  <div class="max-w-2xl mx-auto px-4">
    <h2 class="text-xl font-bold text-gray-900 mb-3">Get in Touch</h2>
    @if($tenant?->address)
    <p class="text-gray-500 mb-1">{{ $tenant->address }}</p>
    @endif
    <p class="text-gray-500">
      @if($tenant?->contact_phone) Tel: {{ $tenant->contact_phone }} @endif
      @if($tenant?->contact_phone && $tenant?->contact_email) &nbsp;·&nbsp; @endif
      @if($tenant?->contact_email) {{ $tenant->contact_email }} @endif
    </p>
    <a href="{{ route('website.contact') }}"
       class="inline-block mt-5 px-6 py-2.5 rounded-lg text-white text-sm font-medium transition-colors hover:opacity-90"
       style="background: {{ $primaryColor }}">
      Send us a message →
    </a>
  </div>
</section>
@endsection
