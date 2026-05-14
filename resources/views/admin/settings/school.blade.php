@extends('layouts.admin')

@section('title', 'School Settings')
@section('page-title', 'Settings')

@section('content')
{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2">
  <a href="{{ route('admin.settings.school') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
    School Profile
  </a>
  <a href="{{ route('admin.settings.account') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    Account
  </a>
</div>

<div class="max-w-2xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

    @if($errors->any())
      <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.school.update') }}" enctype="multipart/form-data" class="space-y-5">
      @csrf @method('PUT')

      {{-- Logo --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">School Logo</label>
        <div class="flex items-center gap-4">
          <div class="h-16 w-16 rounded-xl border border-gray-200 overflow-hidden bg-gray-50 flex items-center justify-center flex-shrink-0">
            @if($tenant->logo)
              <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}" class="h-full w-full object-cover">
            @else
              <span class="text-2xl font-bold text-gray-300">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
            @endif
          </div>
          <div class="flex-1 space-y-2">
            <input type="file" name="logo" accept="image/*"
                   class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                          file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-400">JPG, PNG or GIF — max 2 MB</p>
            @if($tenant->logo)
              <label class="inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                <input type="checkbox" name="remove_logo" value="1" class="rounded border-gray-300">
                Remove current logo
              </label>
            @endif
          </div>
        </div>
      </div>

      {{-- Name --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
        <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
      </div>

      {{-- Address --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input type="text" name="address" value="{{ old('address', $tenant->address) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="e.g. P.O. Box 123, Accra">
      </div>

      {{-- Phone & Contact Phone --}}
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Main Phone</label>
          <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="e.g. 030-000-0000">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
          <input type="text" name="contact_phone" value="{{ old('contact_phone', $tenant->contact_phone) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="School admin direct line">
        </div>
      </div>

      {{-- Contact Email --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Billing / Contact Email</label>
        <input type="email" name="contact_email" value="{{ old('contact_email', $tenant->contact_email) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               placeholder="Receives invoices and subscription alerts">
        <p class="mt-1 text-xs text-gray-400">If set, subscription notifications go here instead of your login email.</p>
      </div>

      {{-- Primary Colour --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Brand Colour</label>
        <div class="flex items-center gap-3">
          <input type="color" name="primary_color" value="{{ old('primary_color', $tenant->primary_color ?? '#3b82f6') }}"
                 class="h-9 w-14 border border-gray-300 rounded-lg cursor-pointer p-0.5">
          <input type="text" id="colorHex" value="{{ old('primary_color', $tenant->primary_color ?? '#3b82f6') }}"
                 class="w-28 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500"
                 readonly>
        </div>
        <p class="mt-1 text-xs text-gray-400">Used on public website and communications.</p>
      </div>

      {{-- Actions --}}
      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Save Changes
        </button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Keep text field in sync with colour picker
  const picker = document.querySelector('input[type="color"][name="primary_color"]');
  const hex    = document.getElementById('colorHex');
  if (picker && hex) {
    picker.addEventListener('input', () => hex.value = picker.value);
  }
</script>
@endpush
