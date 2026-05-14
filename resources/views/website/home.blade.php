@extends('layouts.website')

@section('title', app('currentTenant')?->name . ' — Official Website')

@section('content')
@php $tenant = app('currentTenant'); @endphp

{{-- Hero --}}
<section class="relative bg-gradient-to-br from-blue-700 to-blue-900 text-white overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"><g fill=\"none\" fill-rule=\"evenodd\"><g fill=\"%23ffffff\" fill-opacity=\"1\"><path d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/></g></g></svg>');"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
        @if($tenant?->logo)
            <img src="{{ asset('storage/' . $tenant->logo) }}"
                 alt="{{ $tenant->name }}"
                 class="h-24 w-24 object-contain rounded-full mx-auto mb-6 border-4 border-white/30">
        @endif
        <h1 class="text-4xl sm:text-5xl font-extrabold mb-4 leading-tight">
            Welcome to<br>{{ $tenant?->name }}
        </h1>
        <p class="text-xl text-blue-100 max-w-2xl mx-auto mb-8">
            Excellence in education. Building tomorrow's leaders today.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('website.admissions') }}"
               class="bg-white text-blue-700 font-semibold px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors">
                Apply for Admission
            </a>
            <a href="{{ route('website.about') }}"
               class="border-2 border-white/50 text-white font-semibold px-8 py-3 rounded-xl hover:bg-white/10 transition-colors">
                Learn More
            </a>
        </div>
    </div>
</section>

{{-- Quick info strips --}}
<section class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
            @foreach([
                ['🎓', 'Excellence', 'Top-quality education aligned with GES curriculum'],
                ['🏆', 'Achievement', 'Consistent high performance in BECE & national exams'],
                ['🤝', 'Community', 'Strong school-parent partnership for student success'],
            ] as [$icon, $title, $desc])
                <div class="p-4">
                    <div class="text-3xl mb-2">{{ $icon }}</div>
                    <h3 class="font-semibold text-gray-900 mb-1">{{ $title }}</h3>
                    <p class="text-sm text-gray-500">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- News preview --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <h2 class="text-2xl font-bold text-gray-900">Latest News</h2>
        <a href="{{ route('website.news') }}" class="text-sm text-blue-600 hover:underline font-medium">View all →</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @for($i = 0; $i < 3; $i++)
            <article class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                <div class="h-40 bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center">
                    <svg class="h-12 w-12 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                </div>
                <div class="p-4">
                    <p class="text-xs text-gray-400 mb-1">{{ now()->subDays($i * 5)->format('d M Y') }}</p>
                    <h3 class="font-semibold text-gray-900 text-sm">School News Update</h3>
                    <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                        Stay informed with the latest updates from {{ $tenant?->name }}.
                    </p>
                </div>
            </article>
        @endfor
    </div>
</section>

{{-- CTA --}}
<section class="bg-blue-700 text-white py-16">
    <div class="max-w-3xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Ready to Join Us?</h2>
        <p class="text-blue-100 mb-8">Applications are open for the upcoming academic term. Secure your child's place today.</p>
        <a href="{{ route('website.admissions') }}"
           class="bg-white text-blue-700 font-semibold px-8 py-3 rounded-xl hover:bg-blue-50 inline-block transition-colors">
            Start Application
        </a>
    </div>
</section>

@endsection
