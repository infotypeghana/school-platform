@extends('layouts.website')

@section('title', 'About Us — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

{{-- Hero --}}
<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">About {{ $tenant?->name }}</h1>
    <p class="text-blue-100 text-lg">Excellence in education since our founding. Shaping the future of Ghana, one student at a time.</p>
  </div>
</section>

{{-- Mission & Vision --}}
<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
    <div class="bg-blue-50 rounded-2xl p-8">
      <div class="text-3xl mb-3">🎯</div>
      <h2 class="text-xl font-bold text-blue-900 mb-3">Our Mission</h2>
      <p class="text-gray-600 leading-relaxed">
        To provide quality, holistic education aligned with the Ghana Education Service curriculum,
        nurturing every student's academic, moral, and social development to achieve their fullest potential.
      </p>
    </div>
    <div class="bg-emerald-50 rounded-2xl p-8">
      <div class="text-3xl mb-3">🌟</div>
      <h2 class="text-xl font-bold text-emerald-900 mb-3">Our Vision</h2>
      <p class="text-gray-600 leading-relaxed">
        To be the premier institution of choice in our community — producing well-rounded graduates
        who are equipped to excel in national examinations and contribute positively to society.
      </p>
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
    <p class="text-gray-500 mb-1">{{ $tenant?->address }}</p>
    <p class="text-gray-500">Tel: {{ $tenant?->contact_phone }} &nbsp;·&nbsp; {{ $tenant?->contact_email }}</p>
  </div>
</section>
@endsection
