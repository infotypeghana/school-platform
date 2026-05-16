<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Your School — SchoolMS Ghana</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">

<div class="min-h-screen flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-lg">
    <div class="text-center mb-8">
      <div class="text-2xl font-bold text-gray-900">SchoolMS Ghana</div>
      <h1 class="mt-3 text-3xl font-extrabold text-gray-900">Register Your School</h1>
      <p class="mt-2 text-sm text-gray-500">
        Already registered? <a href="{{ url('/login') }}" class="text-blue-600 hover:underline">Sign in</a>
      </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-8 py-8">

      @if($errors->any())
        <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
          <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('register.school.submit') }}" class="space-y-5">
        @csrf

        {{-- School info --}}
        <div>
          <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">School Information</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">School Name <span class="text-red-500">*</span></label>
              <input name="school_name" value="{{ old('school_name') }}" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                     placeholder="e.g. Accra Academy Junior High School">
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">School Type <span class="text-red-500">*</span></label>
                <select name="school_type" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">Select…</option>
                  <option value="public"        @selected(old('school_type') === 'public')>Public (GES)</option>
                  <option value="private"       @selected(old('school_type') === 'private')>Private</option>
                  <option value="international" @selected(old('school_type') === 'international')>International</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">District <span class="text-red-500">*</span></label>
                <input name="district" value="{{ old('district') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g. Accra Metro">
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Students <span class="text-red-500">*</span></label>
                <input name="estimated_students" type="number" min="10" max="9999"
                       value="{{ old('estimated_students') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g. 250">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">School Address</label>
                <input name="address" value="{{ old('address') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Optional">
              </div>
            </div>
          </div>
        </div>

        <hr class="border-gray-100">

        {{-- Contact info --}}
        <div>
          <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Contact Person</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
              <input name="contact_name" value="{{ old('contact_name') }}" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                     placeholder="Head teacher or registrar">
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                <input name="contact_email" type="email" value="{{ old('contact_email') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="admin@yourschool.edu.gh">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                <input name="contact_phone" type="tel" value="{{ old('contact_phone') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="0241234567">
              </div>
            </div>
          </div>
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition-colors text-sm">
          Submit Registration Request
        </button>

        <p class="text-xs text-gray-400 text-center">
          We'll review your application and send login credentials within 24 hours.
        </p>
      </form>
    </div>
  </div>
</div>

</body>
</html>
