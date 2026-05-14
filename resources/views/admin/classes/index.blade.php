@extends('layouts.admin')

@section('title', 'Classes')
@section('page-title', 'Classes')

@section('content')

<div class="flex items-center justify-between mb-6">
  <p class="text-sm text-gray-500">{{ $classes->count() }} class(es) registered</p>
  <a href="{{ route('admin.classes.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
    + Add Class
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  @forelse($classes as $class)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow">
      <div class="flex items-start justify-between mb-3">
        <div>
          <h3 class="font-semibold text-gray-900">{{ $class->full_name }}</h3>
          <p class="text-xs text-gray-400 mt-0.5">{{ $class->level ?? 'No level set' }}</p>
        </div>
        <div class="flex gap-1">
          <a href="{{ route('admin.classes.edit', $class) }}"
             class="text-xs text-blue-600 hover:underline">Edit</a>
          <span class="text-gray-300">|</span>
          <form method="POST" action="{{ route('admin.classes.destroy', $class) }}"
                onsubmit="return confirm('Delete {{ $class->full_name }}? This cannot be undone.')" class="inline">
            @csrf @method('DELETE')
            <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
          </form>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-xs">
        <div class="bg-blue-50 rounded-lg px-3 py-2">
          <p class="text-gray-400">Students</p>
          <p class="font-bold text-blue-700 text-lg">{{ $class->students_count }}</p>
        </div>
        <div class="bg-violet-50 rounded-lg px-3 py-2">
          <p class="text-gray-400">Subjects</p>
          <p class="font-bold text-violet-700 text-lg">{{ $class->subjects_count }}</p>
        </div>
      </div>

      <div class="mt-3 text-xs text-gray-500">
        <span class="font-medium">Class Teacher:</span>
        {{ $class->classTeacher?->full_name ?? 'Not assigned' }}
      </div>

      <div class="mt-3 flex gap-2">
        <a href="{{ route('admin.classes.show', $class) }}"
           class="flex-1 text-center bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-medium py-1.5 rounded-lg transition-colors">
          View Roster
        </a>
        <a href="{{ route('admin.classes.subjects', $class) }}"
           class="flex-1 text-center bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-medium py-1.5 rounded-lg transition-colors">
          Manage Subjects
        </a>
      </div>
    </div>
  @empty
    <div class="col-span-3 py-16 text-center text-gray-400 text-sm">
      No classes yet. <a href="{{ route('admin.classes.create') }}" class="text-blue-600 hover:underline">Create one →</a>
    </div>
  @endforelse
</div>
@endsection
