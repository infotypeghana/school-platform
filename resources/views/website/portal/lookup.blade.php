@extends('layouts.website')

@section('title', 'Parent Portal')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
  <div class="max-w-md w-full">

    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-2xl mb-4">
        <svg class="w-7 h-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-gray-900">Parent Portal</h1>
      <p class="text-sm text-gray-500 mt-1">
        Check your child's fees, results, and attendance records.
      </p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

      @if(session('success'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg px-4 py-3">
          {{ session('success') }}
        </div>
      @endif

      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('website.portal.lookup') }}" class="space-y-4">
        @csrf

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Admission Number</label>
          <input type="text" name="admission_number" value="{{ old('admission_number') }}"
                 placeholder="e.g. ADM-2024-0001" required autocomplete="off"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Student's Date of Birth</label>
          <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
          <p class="text-xs text-gray-400 mt-1">Used to verify identity — must match school records.</p>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
          View Student Records
        </button>
      </form>

    </div>

    <p class="text-center text-xs text-gray-400 mt-4">
      Having trouble? Contact the school office for your child's admission number.
    </p>

  </div>
</div>
@endsection
