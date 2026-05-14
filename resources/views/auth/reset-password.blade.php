<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set New Password — {{ app('currentTenant')?->name ?? 'SchoolMS' }}</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 flex items-center justify-center p-4">
<div class="w-full max-w-md">
  <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-8 text-center">
      <h1 class="text-xl font-bold text-white">Set New Password</h1>
      <p class="text-blue-200 text-sm mt-1">Choose a strong password for your account</p>
    </div>
    <div class="px-8 py-8">
      @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          @foreach($errors->all() as $e) <p>{{ $e }}</p> @endforeach
        </div>
      @endif
      <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
          <input type="email" name="email" value="{{ old('email', $email) }}" required
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
          <input type="password" name="password" required minlength="8"
                 placeholder="Minimum 8 characters"
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
          <input type="password" name="password_confirmation" required
                 class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
          Reset Password
        </button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
