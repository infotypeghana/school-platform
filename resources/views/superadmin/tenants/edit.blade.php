@extends('layouts.superadmin')

@section('title', 'Edit School')
@section('page-title', 'Edit School')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('superadmin.tenants.update', $tenant) }}"
          enctype="multipart/form-data" class="space-y-4">
      @csrf @method('PUT')

      {{-- Logo upload with current logo preview --}}
      <div x-data="logoEdit(@js($tenant->logo ? asset('storage/' . $tenant->logo) : null))" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">School Logo</label>
        <div class="flex items-center gap-4">
          {{-- Preview / current logo --}}
          <div class="h-16 w-16 rounded-full border-2 flex items-center justify-center overflow-hidden flex-shrink-0"
               :class="currentSrc ? 'border-gray-200' : 'border-dashed border-gray-300 bg-gray-50'">
            <template x-if="currentSrc">
              <img :src="currentSrc" class="h-full w-full object-cover rounded-full">
            </template>
            <template x-if="!currentSrc">
              <svg class="h-7 w-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </template>
          </div>
          <div class="flex-1 space-y-1.5">
            <div class="flex items-center gap-2">
              <label for="logo"
                     class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                {{ $tenant->logo ? 'Change Logo' : 'Upload Logo' }}
              </label>
              @if($tenant->logo)
                <button type="button" @click="removeLogo()"
                        x-show="!logoRemoved"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-red-600 hover:text-red-700 text-xs font-medium hover:bg-red-50 rounded-lg transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Remove
                </button>
              @endif
            </div>
            <input id="logo" type="file" name="logo" accept="image/*"
                   class="sr-only"
                   @change="handleFile($event)">
            <input type="hidden" name="remove_logo" :value="logoRemoved ? '1' : '0'">
            <p class="text-xs text-gray-400">PNG, JPG or WebP · max 2 MB</p>
            <p x-show="fileName" x-text="fileName" class="text-xs text-blue-600 truncate max-w-xs"></p>
            <p x-show="logoRemoved" class="text-xs text-red-500">Logo will be removed on save.</p>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
        <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="text" name="phone" value="{{ old('phone', $tenant->contact_phone ?? $tenant->phone) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input type="text" name="address" value="{{ old('address', $tenant->address) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
        <select name="status" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          @foreach(['trial','active','grace','locked','suspended'] as $s)
            <option value="{{ $s }}" {{ old('status', $tenant->status) === $s ? 'selected' : '' }}>
              {{ ucfirst($s) }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Save Changes
        </button>
        <a href="{{ route('superadmin.tenants.show', $tenant) }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function logoEdit(initialSrc) {
    return {
        currentSrc: initialSrc,
        fileName: '',
        logoRemoved: false,

        handleFile(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.fileName = file.name;
            this.logoRemoved = false;
            const reader = new FileReader();
            reader.onload = e => { this.currentSrc = e.target.result; };
            reader.readAsDataURL(file);
        },

        removeLogo() {
            this.logoRemoved = true;
            this.currentSrc = null;
            this.fileName = '';
            // Clear the file input
            document.getElementById('logo').value = '';
        }
    }
}
</script>
@endpush
