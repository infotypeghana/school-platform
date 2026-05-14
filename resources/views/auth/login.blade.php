<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>
  @if($isSuperAdmin) Super Admin Login @else Admin Login — {{ app('currentTenant')?->name ?? 'SchoolMS' }} @endif
</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">

{{-- Decorative background blobs --}}
<div class="absolute inset-0 overflow-hidden pointer-events-none">
  <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-500 opacity-10 rounded-full blur-3xl"></div>
  <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500 opacity-10 rounded-full blur-3xl"></div>
</div>

<div class="relative w-full max-w-md">

  {{-- Card --}}
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-8 text-center">
      @if($isSuperAdmin)
        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <h1 class="text-xl font-bold text-white">Super Admin Portal</h1>
        <p class="text-blue-200 text-sm mt-1">SchoolMS Ghana Platform</p>
      @else
        @php $tenant = app('currentTenant'); @endphp
        @if($tenant?->logo)
          <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
               class="w-16 h-16 rounded-full object-cover mx-auto mb-3 border-2 border-white/30">
        @else
          <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
            <span class="text-2xl font-bold text-white">
              {{ strtoupper(substr($tenant?->name ?? 'S', 0, 1)) }}
            </span>
          </div>
        @endif
        <h1 class="text-xl font-bold text-white">{{ $tenant?->name ?? 'School Admin' }}</h1>
        <p class="text-blue-200 text-sm mt-1">Admin Portal</p>
      @endif
    </div>

    {{-- Form --}}
    <div class="px-8 py-8">

      {{-- Session status --}}
      @if(session('status'))
        <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
          {{ session('status') }}
        </div>
      @endif

      {{-- Errors --}}
      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
          @endforeach
        </div>
      @endif

      <form method="POST"
            action="{{ $isSuperAdmin ? route('superadmin.login.submit') : route('admin.login.submit') }}"
            class="space-y-5">
        @csrf

        {{-- Email --}}
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">
            Email Address
          </label>
          <input id="email" type="email" name="email"
                 value="{{ old('email') }}"
                 required autofocus autocomplete="email"
                 placeholder="admin@school.edu.gh"
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm
                        focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                        @error('email') border-red-400 bg-red-50 @enderror
                        transition-colors">
        </div>

        {{-- Password --}}
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            @if(!$isSuperAdmin)
              <a href="{{ route('password.request') }}"
                 class="text-xs text-blue-600 hover:underline">Forgot password?</a>
            @endif
          </div>
          <div class="relative">
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm pr-11
                          focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                          transition-colors">
            <button type="button" onclick="togglePassword()"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <svg id="eye-icon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        {{-- Remember me --}}
        <div class="flex items-center gap-2">
          <input id="remember" type="checkbox" name="remember"
                 class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
          <label for="remember" class="text-sm text-gray-600">Keep me signed in</label>
        </div>

        {{-- Submit --}}
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800
                       text-white font-semibold py-3 rounded-xl
                       transition-all duration-150 shadow-sm hover:shadow-md
                       text-sm tracking-wide">
          Sign In
        </button>
      </form>
    </div>

    {{-- Footer --}}
    <div class="px-8 pb-6 text-center">
      <p class="text-xs text-gray-400">
        Powered by <span class="font-semibold text-gray-500">SchoolMS Ghana</span>
        &nbsp;·&nbsp; Secure login
      </p>
    </div>

  </div>

</div>

<script>
function togglePassword() {
  const input = document.getElementById('password');
  input.type  = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
