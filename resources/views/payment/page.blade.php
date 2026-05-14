<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Renew Subscription</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
<div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
  <div class="bg-blue-700 px-6 py-8 text-white text-center">
    <div class="text-4xl mb-2">🔐</div>
    <h1 class="text-xl font-bold mb-1">Renew Subscription</h1>
    <p class="text-blue-200 text-sm">Restore full access to your school portal</p>
  </div>
  <div class="p-6">
    <p class="text-gray-500 text-sm text-center mb-6">
      Click the button below to proceed with secure payment.
    </p>
    <a href="{{ route('payment.initiate', ['slug' => $slug]) }}"
       class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-semibold py-3 rounded-xl transition-colors">
      Pay Now →
    </a>
    <p class="text-center text-xs text-gray-400 mt-4">
      Payments are processed securely. Access is restored immediately upon confirmation.
    </p>
  </div>
</div>
</body>
</html>
