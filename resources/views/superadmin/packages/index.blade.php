@extends('layouts.superadmin')

@section('title', 'Subscription Packages')
@section('page-title', 'Subscription Packages')

@section('content')

<div class="flex items-center justify-between mb-6">
  <p class="text-sm text-gray-500">
    {{ $packages->count() }} package(s) · Per-student termly pricing
  </p>
  <a href="{{ route('superadmin.packages.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
    + New Package
  </a>
</div>

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    {{ session('error') }}
  </div>
@endif

@if($packages->isEmpty())
  <div class="bg-white rounded-xl border border-gray-200 text-center py-16">
    <svg class="h-10 w-10 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
    </svg>
    <p class="text-sm text-gray-500">No packages yet.</p>
    <a href="{{ route('superadmin.packages.create') }}"
       class="mt-3 inline-block text-blue-600 text-sm hover:underline">Create your first package →</a>
  </div>
@else
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @foreach($packages as $pkg)
      <div class="bg-white rounded-xl border {{ $pkg->is_active ? 'border-gray-100' : 'border-dashed border-gray-300 opacity-70' }} shadow-sm overflow-hidden">

        {{-- Header --}}
        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">{{ $pkg->name }}</h3>
            <p class="text-xs text-gray-400 font-mono">{{ $pkg->slug }}</p>
          </div>
          <div class="flex items-center gap-2">
            @if(! $pkg->is_active)
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                Inactive
              </span>
            @endif
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
              {{ $pkg->billingCycleLabel() }}
            </span>
          </div>
        </div>

        {{-- Pricing --}}
        <div class="px-5 py-4">
          <div class="flex items-baseline gap-1 mb-1">
            <span class="text-3xl font-bold text-gray-900">GHS {{ number_format((float)$pkg->price_per_student, 2) }}</span>
            <span class="text-sm text-gray-400">/ student / {{ $pkg->billing_cycle === 'annual' ? 'year' : 'term' }}</span>
          </div>
          <p class="text-xs text-gray-500 mb-3">
            Minimum {{ number_format($pkg->min_students) }} students
            · Floor: GHS {{ number_format($pkg->calculateAmount(0), 2) }}
          </p>

          @if($pkg->description)
            <p class="text-sm text-gray-600 mb-3">{{ $pkg->description }}</p>
          @endif

          @if($pkg->features)
            <ul class="space-y-1">
              @foreach($pkg->features as $feature)
                <li class="flex items-center gap-2 text-xs text-gray-600">
                  <svg class="w-3.5 h-3.5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                  </svg>
                  {{ $feature }}
                </li>
              @endforeach
            </ul>
          @endif
        </div>

        {{-- Footer --}}
        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-2">
          <span class="text-xs text-gray-400">
            {{ $pkg->subscriptions()->count() }} subscription(s)
          </span>
          <div class="flex gap-2">
            <a href="{{ route('superadmin.packages.edit', $pkg) }}"
               class="text-xs text-blue-600 hover:underline font-medium">Edit</a>
            @if($pkg->subscriptions()->doesntExist())
              <form method="POST" action="{{ route('superadmin.packages.destroy', $pkg) }}"
                    onsubmit="return confirm('Delete package \'{{ $pkg->name }}\'?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-red-500 hover:underline font-medium">Delete</button>
              </form>
            @endif
          </div>
        </div>

      </div>
    @endforeach
  </div>
@endif

@endsection
