<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Two-Factor Authentication — SchoolMS</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">

{{-- Decorative background blobs --}}
<div class="absolute inset-0 overflow-hidden pointer-events-none">
  <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-500 opacity-10 rounded-full blur-3xl"></div>
  <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500 opacity-10 rounded-full blur-3xl"></div>
</div>

<div class="relative w-full max-w-md">
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-8 text-center">
      <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <h1 class="text-xl font-bold text-white">Two-Factor Authentication</h1>
      <p class="text-blue-200 text-sm mt-1">Enter the 6-digit code from your authenticator app</p>
    </div>

    {{-- Form --}}
    <div class="px-8 py-8">

      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      <form method="POST" action="{{ route(request()->getHost() && str_starts_with(request()->getHost(), 'superadmin.') ? 'superadmin.2fa.challenge.submit' : 'admin.2fa.challenge.submit') }}" class="space-y-5">
        @csrf

        <div>
          <label for="code" class="block text-sm font-medium text-gray-700 mb-1.5">
            Authenticator Code
          </label>
          <input id="code" type="text" name="code"
                 inputmode="numeric" pattern="[0-9 ]*"
                 maxlength="7"
                 required autofocus autocomplete="one-time-code"
                 placeholder="000 000"
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-center text-2xl
                        tracking-[0.5em] font-mono
                        focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                        @error('code') border-red-400 bg-red-50 @enderror
                        transition-colors">
          <p class="text-xs text-gray-500 mt-1.5 text-center">
            Open your authenticator app (Google Authenticator, Authy, etc.) to get the current code.
          </p>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                       text-white font-semibold py-3 rounded-xl
                       transition-all duration-150 shadow-sm hover:shadow-md
                       text-sm tracking-wide">
          Verify &amp; Sign In
        </button>
      </form>

      <div class="mt-4 text-center">
        <form method="POST" action="{{ route(str_starts_with(request()->getHost(), 'superadmin.') ? 'superadmin.logout' : 'admin.logout') }}">
          @csrf
          <button type="submit" class="text-sm text-gray-500 hover:text-gray-700 underline">
            Cancel and return to login
          </button>
        </form>
      </div>
    </div>

    {{-- Footer --}}
    <div class="px-8 pb-6 text-center">
      <p class="text-xs text-gray-400">
        Powered by <span class="font-semibold text-gray-500">SchoolMS Ghana</span>
        &nbsp;·&nbsp; Secure 2FA
      </p>
    </div>

  </div>
</div>

<script>
// Auto-format code input with space after 3 digits
document.getElementById('code').addEventListener('input', function (e) {
  let val = e.target.value.replace(/\D/g, '').slice(0, 6);
  if (val.length > 3) val = val.slice(0, 3) + ' ' + val.slice(3);
  e.target.value = val;
});
</script>

</body>
</html>
