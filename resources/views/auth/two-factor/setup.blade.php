<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enable Two-Factor Authentication — SchoolMS</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-full bg-gray-50 py-12 px-4">

<div class="max-w-lg mx-auto">

  {{-- Back link --}}
  <div class="mb-6">
    <a href="{{ route('admin.settings.account') }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Back to Account Settings
    </a>
  </div>

  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-6">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </div>
        <div>
          <h1 class="text-lg font-bold text-white">Enable Two-Factor Authentication</h1>
          <p class="text-blue-200 text-sm">Protect your account with an authenticator app</p>
        </div>
      </div>
    </div>

    <div class="px-8 py-8">

      @if($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      {{-- Step 1: Install app --}}
      <div class="flex gap-3 mb-6">
        <div class="w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0 mt-0.5">1</div>
        <div>
          <h2 class="font-semibold text-gray-800">Install an authenticator app</h2>
          <p class="text-sm text-gray-500 mt-1">
            If you don't already have one, download
            <strong>Google Authenticator</strong> or <strong>Authy</strong>
            on your phone.
          </p>
        </div>
      </div>

      {{-- Step 2: Scan QR --}}
      <div class="flex gap-3 mb-6">
        <div class="w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0 mt-0.5">2</div>
        <div class="flex-1">
          <h2 class="font-semibold text-gray-800">Scan this QR code</h2>
          <p class="text-sm text-gray-500 mt-1 mb-4">Open your authenticator app and scan the code below.</p>

          {{-- QR code rendered via Google Charts API (no server-side image generation needed) --}}
          <div class="flex justify-center">
            <div class="border-4 border-white shadow-md rounded-xl inline-block bg-white p-2">
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}"
                   alt="2FA QR Code"
                   class="w-48 h-48"
                   width="200" height="200">
            </div>
          </div>

          {{-- Manual entry fallback --}}
          <details class="mt-4">
            <summary class="text-sm text-blue-600 cursor-pointer hover:underline select-none">
              Can't scan? Enter the key manually
            </summary>
            <div class="mt-2 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3">
              <p class="text-xs text-gray-500 mb-1">Account name: <strong>{{ auth()->user()->email }}</strong></p>
              <p class="text-xs text-gray-500 mb-1">Type: <strong>Time-based (TOTP)</strong></p>
              <p class="text-xs text-gray-500 mb-2">Secret key:</p>
              <code class="text-sm font-mono bg-white border border-gray-300 rounded px-3 py-1.5 block break-all select-all">
                {{ wordwrap($secret, 4, ' ', true) }}
              </code>
            </div>
          </details>
        </div>
      </div>

      {{-- Step 3: Confirm code --}}
      <div class="flex gap-3">
        <div class="w-7 h-7 rounded-full bg-blue-600 text-white text-sm font-bold flex items-center justify-center flex-shrink-0 mt-0.5">3</div>
        <div class="flex-1">
          <h2 class="font-semibold text-gray-800">Enter the 6-digit code to confirm</h2>
          <p class="text-sm text-gray-500 mt-1 mb-3">
            Type the code shown in your authenticator app to verify the setup.
          </p>

          <form method="POST" action="{{ route('admin.2fa.confirm') }}" class="space-y-4">
            @csrf
            <input id="code" type="text" name="code"
                   inputmode="numeric" pattern="[0-9 ]*"
                   maxlength="7"
                   required autofocus autocomplete="one-time-code"
                   placeholder="000 000"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-center text-xl
                          tracking-[0.4em] font-mono
                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                          @error('code') border-red-400 bg-red-50 @enderror
                          transition-colors">

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                           text-white font-semibold py-3 rounded-xl
                           transition-all duration-150 shadow-sm hover:shadow-md text-sm">
              Enable Two-Factor Authentication
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>

</div>

<script>
document.getElementById('code').addEventListener('input', function (e) {
  let val = e.target.value.replace(/\D/g, '').slice(0, 6);
  if (val.length > 3) val = val.slice(0, 3) + ' ' + val.slice(3);
  e.target.value = val;
});
</script>

</body>
</html>
