@extends('layouts.admin')

@section('title', 'Website Content')
@section('page-title', 'Settings')

@section('content')
@include('admin.settings._nav')

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
    {{ session('success') }}
  </div>
@endif

@if($errors->any())
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('admin.settings.website.update') }}"
      enctype="multipart/form-data" class="space-y-6 max-w-3xl">
  @csrf @method('PUT')

  {{-- ── Hero Section ───────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">Hero Section</h2>
    <p class="text-xs text-gray-400 mb-5">Shown on the homepage banner. School name comes from School Profile.</p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tagline <span class="text-gray-400 font-normal">(small text above school name)</span></label>
        <input type="text" name="hero_tagline"
               value="{{ old('hero_tagline', $tenant->wc('hero_tagline', 'Est. · Ghana Education Service')) }}"
               maxlength="150"
               placeholder="e.g. Est. 1997 · Greater Accra Region"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">Max 150 characters.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Hero Subtitle</label>
        <textarea name="hero_subtitle" rows="2" maxlength="300"
                  placeholder="e.g. Excellence in education. Building tomorrow's leaders today."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('hero_subtitle', $tenant->wc('hero_subtitle', 'Excellence in education. Building tomorrow\'s leaders today.')) }}</textarea>
        <p class="text-xs text-gray-400 mt-1">Max 300 characters.</p>
      </div>
    </div>
  </div>

  {{-- ── School Stats ────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">Homepage Stats Strip</h2>
    <p class="text-xs text-gray-400 mb-5">Numbers displayed beneath the hero on the home page.</p>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase tracking-wide">Established Year</label>
        <input type="text" name="established_year"
               value="{{ old('established_year', $tenant->wc('established_year', '')) }}"
               placeholder="e.g. 1997" maxlength="10"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase tracking-wide">Students Enrolled</label>
        <input type="text" name="stats_students"
               value="{{ old('stats_students', $tenant->wc('stats_students', '')) }}"
               placeholder="e.g. 487" maxlength="20"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase tracking-wide">Qualified Staff</label>
        <input type="text" name="stats_staff"
               value="{{ old('stats_staff', $tenant->wc('stats_staff', '')) }}"
               placeholder="e.g. 32" maxlength="20"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1 uppercase tracking-wide">BECE Pass Rate (%)</label>
        <input type="text" name="stats_bece_rate"
               value="{{ old('stats_bece_rate', $tenant->wc('stats_bece_rate', '')) }}"
               placeholder="e.g. 94" maxlength="10"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
    </div>
  </div>

  {{-- ── About Page ─────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">About Page</h2>
    <p class="text-xs text-gray-400 mb-5">Content shown on the About Us page.</p>

    <div class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">About Intro Paragraph</label>
        <textarea name="about_intro" rows="4" maxlength="2000"
                  placeholder="Write a brief history and introduction to your school..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('about_intro', $tenant->wc('about_intro', '')) }}</textarea>
        <p class="text-xs text-gray-400 mt-1">Max 2,000 characters.</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">🎯 Mission Statement</label>
          <textarea name="mission" rows="4" maxlength="1000"
                    placeholder="Our mission is to..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('mission', $tenant->wc('mission', 'To provide quality, holistic education aligned with the Ghana Education Service curriculum, nurturing every student\'s academic, moral, and social development to achieve their fullest potential.')) }}</textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">🌟 Vision Statement</label>
          <textarea name="vision" rows="4" maxlength="1000"
                    placeholder="Our vision is to..."
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('vision', $tenant->wc('vision', 'To be the premier institution of choice in our community — producing well-rounded graduates who are equipped to excel in national examinations and contribute positively to society.')) }}</textarea>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Admissions Notice ───────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">Admissions</h2>
    <p class="text-xs text-gray-400 mb-5">Controls the admissions CTA on the public website.</p>

    <div class="space-y-4">
      <div class="flex items-center gap-3">
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="hidden" name="admissions_open" value="0">
          <input type="checkbox" name="admissions_open" value="1" class="sr-only peer"
                 {{ $tenant->wc('admissions_open', true) ? 'checked' : '' }}>
          <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer
                      peer-checked:after:translate-x-full peer-checked:after:border-white
                      after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                      after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5
                      after:transition-all peer-checked:bg-blue-600"></div>
        </label>
        <span class="text-sm font-medium text-gray-700">Admissions are currently open</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Admissions Notice / Note</label>
        <textarea name="admissions_note" rows="2" maxlength="500"
                  placeholder="e.g. Applications now open for 2026/2027. Limited spaces available."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('admissions_note', $tenant->wc('admissions_note', '')) }}</textarea>
      </div>
    </div>
  </div>

  {{-- ── Gallery ─────────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">Photo Gallery</h2>
    <p class="text-xs text-gray-400 mb-5">Photos shown on the Gallery page. Upload JPG or PNG, max 5 MB each.</p>

    @php $galleryImages = $tenant->wc('gallery_images', []); @endphp

    {{-- Existing images --}}
    @if(!empty($galleryImages))
      <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-4">
        @foreach($galleryImages as $img)
          <div class="relative group rounded-lg overflow-hidden border border-gray-200 aspect-square bg-gray-50">
            <img src="{{ asset('storage/' . $img) }}" alt="Gallery image"
                 class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
              <label class="cursor-pointer flex items-center gap-1 text-white text-xs font-medium">
                <input type="checkbox" name="remove_gallery[]" value="{{ $img }}"
                       class="rounded border-gray-300 text-red-500 accent-red-500">
                Remove
              </label>
            </div>
          </div>
        @endforeach
      </div>
      <p class="text-xs text-gray-400 mb-4">Hover an image and check "Remove" to delete it on save.</p>
    @else
      <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center mb-4">
        <svg class="mx-auto h-10 w-10 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <p class="text-sm text-gray-400">No photos yet. Upload some below.</p>
      </div>
    @endif

    {{-- Upload new images --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Upload New Photos</label>
      <input type="file" name="gallery_images[]" accept="image/*" multiple
             class="block w-full text-sm text-gray-500
                    file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                    file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                    hover:file:bg-blue-100">
      <p class="text-xs text-gray-400 mt-1">Select multiple files at once. Each max 5 MB. JPG or PNG.</p>
    </div>
  </div>

  {{-- ── Footer ──────────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-bold text-gray-800 mb-1">Footer</h2>
    <p class="text-xs text-gray-400 mb-4">Short tagline shown in the website footer.</p>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Footer Tagline</label>
      <input type="text" name="footer_tagline"
             value="{{ old('footer_tagline', $tenant->wc('footer_tagline', 'Shaping tomorrow\'s leaders today.')) }}"
             maxlength="200"
             placeholder="e.g. Shaping tomorrow's leaders today."
             class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    </div>
  </div>

  {{-- Actions --}}
  <div class="flex items-center gap-3 pb-6">
    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
      Save Website Content
    </button>
    <a href="{{ $tenant->slug ? 'http://' . $tenant->slug . '.' . config('app.domain') : '#' }}"
       target="_blank"
       class="px-5 py-2.5 text-sm border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-1.5">
      🌐 Preview Website
    </a>
  </div>

</form>
@endsection
