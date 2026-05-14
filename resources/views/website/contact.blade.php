@extends('layouts.website')

@section('title', 'Contact — ' . (app('currentTenant')?->name ?? 'School'))

@section('content')
@php $tenant = app('currentTenant'); @endphp

<section class="bg-gradient-to-br from-blue-700 to-blue-900 text-white py-20 text-center">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-4xl font-extrabold mb-4">Contact Us</h1>
    <p class="text-blue-100">We'd love to hear from you. Reach out anytime.</p>
  </div>
</section>

<section class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

    {{-- Info --}}
    <div class="space-y-6">
      <div>
        <h2 class="text-xl font-bold text-gray-900 mb-4">School Information</h2>
        <div class="space-y-4">
          @foreach([
            ['📍', 'Address', $tenant?->address ?? 'Ghana'],
            ['📞', 'Phone',   $tenant?->contact_phone ?? '—'],
            ['✉️', 'Email',   $tenant?->contact_email ?? '—'],
          ] as [$icon, $label, $value])
            <div class="flex items-start gap-3">
              <span class="text-xl">{{ $icon }}</span>
              <div>
                <p class="text-xs text-gray-400 font-medium">{{ $label }}</p>
                <p class="text-gray-800">{{ $value }}</p>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="bg-blue-50 rounded-2xl p-5">
        <h3 class="font-semibold text-blue-900 mb-2">Office Hours</h3>
        <div class="text-sm text-gray-600 space-y-1">
          <div class="flex justify-between">
            <span>Monday – Friday</span>
            <span class="font-medium">7:30 AM – 4:00 PM</span>
          </div>
          <div class="flex justify-between">
            <span>Saturday</span>
            <span class="font-medium">8:00 AM – 12:00 PM</span>
          </div>
          <div class="flex justify-between text-gray-400">
            <span>Sunday</span>
            <span>Closed</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Contact form --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8">
      <h2 class="text-xl font-bold text-gray-900 mb-5">Send a Message</h2>

      @if(session('contact_sent'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-3 text-sm">
          Thank you! Your message has been sent. We'll respond shortly.
        </div>
      @endif

      <form method="POST" action="{{ route('website.contact.send') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Your Name *</label>
            <input type="text" name="name" required value="{{ old('name') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phone / Email *</label>
            <input type="text" name="contact" required value="{{ old('contact') }}"
                   class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
          <input type="text" name="subject" required value="{{ old('subject') }}"
                 class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
          <textarea name="message" rows="5" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 resize-none">{{ old('message') }}</textarea>
        </div>
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
          Send Message
        </button>
      </form>
    </div>
  </div>
</section>
@endsection
