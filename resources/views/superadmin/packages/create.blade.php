@extends('layouts.superadmin')

@section('title', 'New Package')
@section('page-title', 'New Subscription Package')

@section('content')

<div class="flex items-center gap-3 mb-6">
  <a href="{{ route('superadmin.packages') }}"
     class="text-gray-400 hover:text-gray-600 transition-colors text-sm">← Packages</a>
</div>

<div class="max-w-2xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <form method="POST" action="{{ route('superadmin.packages.store') }}">
      @csrf
      @include('superadmin.packages._form')
      <div class="flex gap-3 pt-5 border-t border-gray-100 mt-5">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Create Package
        </button>
        <a href="{{ route('superadmin.packages') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>

@endsection
