<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — {{ app('currentTenant')?->name ?? 'SchoolMS' }}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">
<div class="w-full max-w-md">
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-8 text-center">
      <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-3">
        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
      </div>
      <h1 class="text-xl font-bold text-white">Reset Password</h1>
      <p class="text-blue-200 text-sm mt-1">Enter your email to receive a reset link</p>
    </div>
    <div class="px-8 py-8">
      @if(session('status'))
        <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl px-4 py-4 text-sm text-center">
          {{ session('status') }}
        </div>
      @endif
      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
        </div>
      @endif
      <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
          <input type="email" name="email" value="{{ old('email') }}" required autofocus
                 placeholder="your@email.com"
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
          Send Reset Link
        </button>
      </form>
      <div class="mt-5 text-center">
        <a href="{{ route('admin.login') }}" class="text-sm text-blue-600 hover:underline">← Back to login</a>
      </div>
    </div>
  </div>
</div>
</body>
</html>
