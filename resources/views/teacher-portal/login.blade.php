<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Portal — {{ app('currentTenant')?->name ?? 'School' }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">

@php $tenant = app('currentTenant'); @endphp

<div class="max-w-sm w-full">

    {{-- School branding --}}
    <div class="text-center mb-6">
        @if($tenant?->logo)
            <img src="{{ asset('storage/' . $tenant->logo) }}" alt="{{ $tenant->name }}"
                 class="h-16 w-16 mx-auto rounded-2xl object-contain shadow mb-3">
        @else
            <div class="h-16 w-16 mx-auto rounded-2xl flex items-center justify-center shadow mb-3"
                 style="background-color: {{ $tenant?->primary_color ?? '#1a56db' }}">
                <span class="text-2xl font-bold text-white">
                    {{ strtoupper(substr($tenant?->name ?? 'S', 0, 2)) }}
                </span>
            </div>
        @endif
        <h1 class="text-lg font-bold text-gray-900">{{ $tenant?->name ?? 'School' }}</h1>
        <p class="text-sm text-gray-500">Teacher Portal</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-7">
        <h2 class="text-base font-semibold text-gray-900 mb-5">Sign in to your account</h2>

        @if(session('success'))
            <div class="mb-4 bg-emerald-50 text-emerald-700 border border-emerald-200 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-red-50 text-red-700 border border-red-200 px-4 py-3 rounded-lg text-sm">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('teacher.portal.login.submit') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Email address</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="{{ old('email') }}"
                       class="w-full px-3 py-2.5 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                              {{ $errors->has('email') ? 'border-red-300 bg-red-50' : 'border-gray-300' }}">
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Sign in
            </button>
        </form>

        <p class="text-xs text-gray-400 text-center mt-5">
            Forgot your password? Contact your school administrator.
        </p>
    </div>

    <p class="text-center text-xs text-gray-400 mt-4">
        <a href="{{ route('website.home') }}" class="hover:text-gray-600">← Back to school website</a>
    </p>
</div>

</body>
</html>
