@use('Illuminate\Support\Facades\Storage')
@extends('layouts.admin')

@section('title', 'Edit Teacher')
@section('page-title', 'Edit Teacher')

@section('content')
<div class="max-w-2xl">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    @if($errors->any())
      <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="POST" action="{{ route('admin.teachers.update', $teacher) }}" class="space-y-4" enctype="multipart/form-data">
      @csrf @method('PUT')
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
          <input type="text" name="first_name" value="{{ old('first_name', $teacher->first_name) }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
          <input type="text" name="last_name" value="{{ old('last_name', $teacher->last_name) }}" required
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Staff ID</label>
          <input type="text" name="staff_id" value="{{ old('staff_id', $teacher->staff_id) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
          <select name="gender" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="">— Select —</option>
            <option value="male"   {{ old('gender', $teacher->gender) === 'male'   ? 'selected' : '' }}>Male</option>
            <option value="female" {{ old('gender', $teacher->gender) === 'female' ? 'selected' : '' }}>Female</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input type="text" name="phone" value="{{ old('phone', $teacher->phone) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input type="email" name="email" value="{{ old('email', $teacher->email) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Qualification</label>
          <input type="text" name="qualification" value="{{ old('qualification', $teacher->qualification) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Specialization</label>
          <input type="text" name="specialization" value="{{ old('specialization', $teacher->specialization) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date Joined</label>
          <input type="date" name="joined_date" value="{{ old('joined_date', $teacher->joined_date?->format('Y-m-d')) }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
          <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option value="active"   {{ old('status', $teacher->status) === 'active'   ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $teacher->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
          </select>
        </div>
      </div>
      {{-- Photo upload --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
        @if($teacher->photo)
          <div class="flex items-center gap-4 mb-3">
            <img src="{{ Storage::url($teacher->photo) }}" alt="{{ $teacher->full_name }}"
                 class="h-16 w-16 rounded-xl object-cover border border-gray-200">
            <div>
              <p class="text-xs text-gray-500 mb-1">Current photo</p>
              <label class="inline-flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                <input type="checkbox" name="remove_photo" value="1" class="rounded">
                Remove photo
              </label>
            </div>
          </div>
        @endif
        <input type="file" name="photo" accept="image/*"
               class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0
                      file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700
                      hover:file:bg-emerald-100 cursor-pointer">
        <p class="mt-1 text-xs text-gray-400">Max 2 MB. Leave blank to keep current photo.</p>
        @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">Save Changes</button>
        <a href="{{ route('admin.teachers') }}" class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
