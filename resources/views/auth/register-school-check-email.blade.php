<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check Your Email — SchoolMS Ghana</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">

<div class="min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">

    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-6">
      <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
      </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Check your email</h1>
    <p class="text-gray-500 text-sm max-w-sm mx-auto mb-6">
      We've sent a verification link to your email address. Please click the link to complete your registration.
      The link expires in <strong>60 minutes</strong>.
    </p>

    <p class="text-xs text-gray-400">
      Didn't receive it? Check your spam folder. If you still can't find it,
      <a href="{{ route('register.school') }}" class="text-blue-600 hover:underline">register again</a>.
    </p>

  </div>
</div>

</body>
</html>
