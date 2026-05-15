@extends('layouts.admin')

@section('title', 'Account Settings')
@section('page-title', 'Settings')

@section('content')
{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  <a href="{{ route('admin.settings.school') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    School Profile
  </a>
  <a href="{{ route('admin.settings.grading') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    Grading
  </a>
  <a href="{{ route('admin.settings.website') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
    Website
  </a>
  <a href="{{ route('admin.settings.account') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
    Account
  </a>
</div>

<div class="max-w-md">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-5">Change Password</h2>

    @if($errors->any())
      <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.account.update') }}" class="space-y-4">
      @csrf @method('PUT')

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password *</label>
        <input type="password" name="current_password" required autocomplete="current-password"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        @error('current_password')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
        <input type="password" name="password" required autocomplete="new-password"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        <p class="mt-1 text-xs text-gray-400">Minimum 8 characters.</p>
        @error('password')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password *</label>
        <input type="password" name="password_confirmation" required autocomplete="new-password"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
      </div>

      <div class="pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Update Password
        </button>
      </div>
    </form>
  </div>

  <div class="mt-5 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h2 class="text-sm font-semibold text-gray-700 mb-1">Login Email</h2>
    <p class="text-sm text-gray-500">{{ auth()->user()->email }}</p>
    <p class="text-xs text-gray-400 mt-1">To change your login email, contact your system administrator.</p>
  </div>

  {{-- Two-Factor Authentication ────────────────────────────────────────────── --}}
  <div class="mt-5 bg-white rounded-xl border border-gray-100 shadow-sm p-6" x-data="{ confirmDisable: false }">
    <div class="flex items-start justify-between gap-4 mb-3">
      <div>
        <h2 class="text-sm font-semibold text-gray-700">Two-Factor Authentication</h2>
        <p class="text-xs text-gray-500 mt-0.5">
          Add an extra layer of security to your account using an authenticator app.
        </p>
      </div>
      @if(auth()->user()->two_factor_enabled)
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 shrink-0">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
          </svg>
          Enabled
        </span>
      @else
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 shrink-0">
          Disabled
        </span>
      @endif
    </div>

    @if(auth()->user()->two_factor_enabled)
      {{-- Disable 2FA --}}
      <div x-show="!confirmDisable">
        <button @click="confirmDisable = true"
                class="text-sm text-red-600 hover:text-red-700 font-medium underline">
          Disable Two-Factor Authentication
        </button>
      </div>
      <div x-show="confirmDisable" x-cloak>
        <p class="text-sm text-gray-600 mb-3">
          Enter your current password to confirm disabling 2FA.
        </p>
        <form method="POST" action="{{ route('admin.2fa.disable') }}" class="space-y-3">
          @csrf
          <input type="password" name="password" required autocomplete="current-password"
                 placeholder="Current password"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400
                        @error('password') border-red-400 bg-red-50 @enderror">
          @error('password')
            <p class="text-xs text-red-600">{{ $message }}</p>
          @enderror
          <div class="flex gap-2">
            <button type="submit"
                    class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg transition-colors text-sm">
              Disable 2FA
            </button>
            <button type="button" @click="confirmDisable = false"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2 rounded-lg transition-colors text-sm">
              Cancel
            </button>
          </div>
        </form>
      </div>
    @else
      {{-- Enable 2FA --}}
      <a href="{{ route('admin.2fa.setup') }}"
         class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2.5 rounded-lg transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        Set Up Two-Factor Authentication
      </a>
    @endif
  </div>
</div>
@endsection
