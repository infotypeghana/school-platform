@extends('layouts.admin')

@section('title', $device ? 'Edit Device' : 'Register Device')
@section('page-title', $device ? 'Edit Biometric Device' : 'Register Biometric Device')

@section('content')
<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    @php $action = $device ? route('admin.biometric.update', $device) : route('admin.biometric.store'); @endphp

    <form method="POST" action="{{ $action }}" class="space-y-4">
      @csrf
      @if($device) @method('PUT') @endif

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Device Name *</label>
        <input type="text" name="name" value="{{ old('name', $device?->name) }}" required
               placeholder="e.g. Main Gate, Staff Room"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
          <input type="text" name="model" value="{{ old('model', $device?->model) }}"
                 placeholder="e.g. ZKTeco K40"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
          <input type="text" name="location" value="{{ old('location', $device?->location) }}"
                 placeholder="e.g. Block A"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>

      <hr class="border-gray-100">

      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">ADMS (Push — recommended)</p>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Device Serial Number</label>
        <input type="text" name="device_serial" value="{{ old('device_serial', $device?->device_serial) }}"
               placeholder="e.g. ABCD123456"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">Found on device: Menu → Info → Device SN. Required for ADMS push.</p>
      </div>

      <hr class="border-gray-100">

      <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">TCP Pull (optional — for manual sync)</p>
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
          <input type="text" name="ip_address" value="{{ old('ip_address', $device?->ip_address) }}"
                 placeholder="192.168.1.201"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
          <input type="number" name="port" value="{{ old('port', $device?->port ?? 4370) }}"
                 min="1" max="65535"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Device Password</label>
        <input type="number" name="password" value="{{ old('password', $device?->password ?? 0) }}"
               min="0"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="text-xs text-gray-400 mt-1">Usually 0 (none). Change only if you set a password on the device.</p>
      </div>

      <hr class="border-gray-100">

      <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" id="is_active" name="is_active" value="1"
               {{ old('is_active', $device?->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="is_active" class="text-sm font-medium text-gray-700">Device is active</label>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors">
          {{ $device ? 'Save Changes' : 'Register Device' }}
        </button>
        <a href="{{ route('admin.biometric.index') }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
