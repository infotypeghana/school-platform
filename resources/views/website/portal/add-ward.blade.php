@extends('layouts.website')

@section('title', 'Add Another Child — Parent Portal')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
  <div class="max-w-md w-full">

    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-100 rounded-2xl mb-4">
        <svg class="w-7 h-7 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-gray-900">Add Another Child</h1>
      <p class="text-sm text-gray-500 mt-1">
        Enter your second child's details to view their records alongside the first.
      </p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
          {{ $errors->first() }}
        </div>
      @endif

      {{-- Uses the same lookup endpoint — it will add to the session list --}}
      <form method="POST" action="{{ route('website.portal.lookup') }}" class="space-y-4">
        @csrf

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Admission Number</label>
          <input type="text" name="admission_number" value="{{ old('admission_number') }}"
                 placeholder="e.g. ADM-2024-0002" required autocomplete="off"
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Student's Date of Birth</label>
          <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required
                 class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition">
          <p class="text-xs text-gray-400 mt-1">Must match the date of birth on school records.</p>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
          Add Child
        </button>
      </form>

    </div>

    <div class="text-center mt-4">
      <a href="{{ route('website.portal.dashboard') }}"
         class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
        ← Back to dashboard
      </a>
    </div>

  </div>
</div>
@endsection
