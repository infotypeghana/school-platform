@extends('layouts.website')

@section('title', 'Admissions — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">Apply for Admission</h1>
    <p class="text-blue-100 text-lg">
      {{ $term ? 'Applications open for ' . $term->term_name . ' · ' . $term->academicYear?->year_label : 'Applications are currently open.' }}
    </p>
  </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

  {{-- Success message --}}
  @if(session('applied'))
    <div class="mb-8 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-6 py-5">
      <h3 class="font-bold text-lg mb-1">✅ Application Submitted!</h3>
      <p class="text-sm">Thank you for applying to {{ $tenant?->name }}. We will review your application and contact you via the phone number provided. Please keep it reachable.</p>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

    {{-- Info sidebar --}}
    <div class="lg:col-span-1 space-y-5">
      <div class="bg-blue-50 rounded-2xl p-6">
        <h3 class="font-bold text-blue-900 mb-3">📋 Requirements</h3>
        <ul class="text-sm text-gray-600 space-y-2">
          <li>✓ Completed application form</li>
          <li>✓ Birth certificate (original)</li>
          <li>✓ Previous school report card</li>
          <li>✓ 2 passport photographs</li>
          <li>✓ Parent/guardian ID</li>
        </ul>
      </div>
      <div class="bg-gray-50 rounded-2xl p-6">
        <h3 class="font-bold text-gray-900 mb-2">📞 Contact Admissions</h3>
        <p class="text-sm text-gray-600">{{ $tenant?->contact_phone }}</p>
        <p class="text-sm text-gray-500">{{ $tenant?->contact_email }}</p>
        <p class="text-sm text-gray-500 mt-2">{{ $tenant?->address }}</p>
      </div>
    </div>

    {{-- Application form --}}
    <div class="lg:col-span-2">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
        <h2 class="text-xl font-bold text-gray-900 mb-6">Online Application Form</h2>

        @if($errors->any())
          <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        <form method="POST" action="{{ route('website.admissions.apply') }}" class="space-y-5">
          @csrf

          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Student Information</p>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
              <input type="text" name="first_name" value="{{ old('first_name') }}" required
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
              <input type="text" name="last_name" value="{{ old('last_name') }}" required
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth *</label>
              <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" required
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Gender *</label>
              <select name="gender" required
                      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">— Select —</option>
                <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>Female</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Class Applying For *</label>
            <input type="text" name="desired_class" value="{{ old('desired_class') }}" required
                   placeholder="e.g. Basic 1, JHS 1, KG 2"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
          </div>

          <hr class="border-gray-100">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Parent / Guardian Information</p>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Name *</label>
              <input type="text" name="guardian_name" value="{{ old('guardian_name') }}" required
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Phone *</label>
              <input type="text" name="guardian_phone" value="{{ old('guardian_phone') }}" required
                     placeholder="0244 000 000"
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Guardian Email</label>
            <input type="email" name="guardian_email" value="{{ old('guardian_email') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Home Address</label>
            <input type="text" name="address" value="{{ old('address') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Message / Additional Notes</label>
            <textarea name="message" rows="3"
                      class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('message') }}</textarea>
          </div>

          <button type="submit"
                  class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
            Submit Application
          </button>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
