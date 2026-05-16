<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Link Expired — SchoolMS Ghana</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">

<div class="min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">

    <div class="inline-flex items-center justify-center w-16 h-16 bg-amber-100 rounded-full mb-6">
      <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
    </div>

    <h1 class="text-2xl font-bold text-gray-900 mb-2">Verification link expired</h1>
    <p class="text-gray-500 text-sm max-w-sm mx-auto mb-6">
      This verification link has expired or has already been used.
      Verification links are valid for <strong>60 minutes</strong> from when you submitted the form.
    </p>

    <a href="{{ route('register.school') }}"
       class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors text-sm">
      Register again
    </a>

  </div>
</div>

</body>
</html>
