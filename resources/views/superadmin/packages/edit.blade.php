@extends('layouts.superadmin')

@section('title', 'Edit Package')
@section('page-title', 'Edit Package')

@section('content')

<div class="flex items-center gap-3 mb-6">
  <a href="{{ route('superadmin.packages') }}"
     class="text-gray-400 hover:text-gray-600 transition-colors text-sm">← Packages</a>
  <span class="text-gray-300">/</span>
  <span class="text-sm text-gray-600 font-medium">{{ $package->name }}</span>
</div>

@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif

<div class="max-w-2xl space-y-5">

  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <form method="POST" action="{{ route('superadmin.packages.update', $package) }}">
      @csrf @method('PUT')
      @include('superadmin.packages._form')
      <div class="flex gap-3 pt-5 border-t border-gray-100 mt-5">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Save Changes
        </button>
        <a href="{{ route('superadmin.packages') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>

  {{-- Usage stats --}}
  @php $subCount = $package->subscriptions()->count(); @endphp
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">Usage</h3>
    <p class="text-sm text-gray-600">
      This package is attached to <strong>{{ $subCount }}</strong> subscription(s).
      @if($subCount > 0)
        <span class="text-amber-600">Editing pricing will not retroactively change existing subscription invoices — amounts are snapshotted at creation time.</span>
      @endif
    </p>

    @if($subCount === 0)
      <div class="mt-4 pt-4 border-t border-gray-100">
        <form method="POST" action="{{ route('superadmin.packages.destroy', $package) }}"
              onsubmit="return confirm('Permanently delete package \'{{ $package->name }}\'?')">
          @csrf @method('DELETE')
          <button type="submit"
                  class="text-sm text-red-600 hover:text-red-700 font-medium">
            Delete this package
          </button>
        </form>
      </div>
    @endif
  </div>

</div>

@endsection
