<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') — SchoolMS Ghana</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full">
    <div class="min-h-full flex flex-col items-center justify-center px-6 py-24">
        <div class="text-center max-w-lg">
            {{-- Logo / Brand --}}
            <div class="mb-8">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-indigo-600 shadow-lg">
                    <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055
                                 a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                </div>
            </div>

            {{-- Error code --}}
            <p class="text-8xl font-extrabold text-indigo-600 mb-4">@yield('code')</p>

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-gray-900 mb-3">@yield('heading')</h1>

            {{-- Message --}}
            <p class="text-gray-500 mb-10">@yield('message')</p>

            {{-- Actions --}}
            @yield('actions')
        </div>
    </div>
</body>
</html>
