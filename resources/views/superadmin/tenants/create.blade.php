@extends('layouts.superadmin')

@section('title', 'Add School')
@section('page-title', 'Add School')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('superadmin.tenants.store') }}"
          enctype="multipart/form-data" class="space-y-4">
      @csrf

      {{-- Logo upload --}}
      <div x-data="logoUpload()" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">School Logo</label>
        <div class="flex items-center gap-4">
          {{-- Preview circle --}}
          <div class="h-16 w-16 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0 bg-gray-50">
            <template x-if="preview">
              <img :src="preview" class="h-full w-full object-cover rounded-full">
            </template>
            <template x-if="!preview">
              <svg class="h-7 w-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </template>
          </div>
          <div class="flex-1">
            <label for="logo"
                   class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
              </svg>
              Choose Image
            </label>
            <input id="logo" type="file" name="logo" accept="image/*"
                   class="sr-only"
                   @change="handleFile($event)">
            <p class="text-xs text-gray-400 mt-1">PNG, JPG or WebP · max 2 MB</p>
            <p x-show="fileName" x-text="fileName" class="text-xs text-blue-600 mt-0.5 truncate max-w-xs"></p>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">School Name *</label>
        <input type="text" name="name" value="{{ old('name') }}" required
               placeholder="e.g. Accra Academy"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email *</label>
        <input type="email" name="email" value="{{ old('email') }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">Used for subscription notifications.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
        <input type="text" name="phone" value="{{ old('phone') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input type="text" name="address" value="{{ old('address') }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div class="bg-blue-50 rounded-lg p-3 text-sm text-blue-700">
        A <strong>free trial subscription</strong> will be automatically created for the current academic term.
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Create School
        </button>
        <a href="{{ route('superadmin.tenants.index') }}"
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
function logoUpload() {
    return {
        preview: null,
        fileName: '',
        handleFile(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.fileName = file.name;
            const reader = new FileReader();
            reader.onload = e => { this.preview = e.target.result; };
            reader.readAsDataURL(file);
        }
    }
}
</script>
@endpush
