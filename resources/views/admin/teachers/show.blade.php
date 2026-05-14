@use('Illuminate\Support\Facades\Storage')
@extends('layouts.admin')

@section('title', $teacher->full_name)
@section('page-title', 'Teacher Profile')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- ── Breadcrumb ───────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('admin.teachers') }}" class="hover:text-blue-600 transition-colors">Teachers</a>
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-900 font-medium">{{ $teacher->full_name }}</span>
    </div>

    {{-- ── Profile header ───────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-5 flex items-center justify-between gap-6 flex-wrap">
        <div class="flex items-center gap-5">
            {{-- Photo or initials avatar --}}
            @if($teacher->photo)
                <img src="{{ Storage::url($teacher->photo) }}" alt="{{ $teacher->full_name }}"
                     class="h-16 w-16 rounded-2xl object-cover border border-gray-200 flex-shrink-0">
            @else
                <div class="h-16 w-16 rounded-2xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-2xl font-bold text-emerald-700">
                        {{ strtoupper(substr($teacher->first_name, 0, 1) . substr($teacher->last_name, 0, 1)) }}
                    </span>
                </div>
            @endif
            <div>
                <h1 class="text-xl font-bold text-gray-900">{{ $teacher->full_name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ $teacher->specialization ?? 'Teacher' }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                 {{ $teacher->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst($teacher->status) }}
                    </span>
                    @if($teacher->staff_id)
                        <span class="text-xs text-gray-400">{{ $teacher->staff_id }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <a href="{{ route('admin.teachers.edit', $teacher) }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50
                      text-gray-700 text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Personal details ─────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-6">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">Personal Information</h2>
                </div>
                <dl class="divide-y divide-gray-50">
                    @foreach([
                        ['Full Name',        $teacher->full_name],
                        ['Gender',           ucfirst($teacher->gender ?? '—')],
                        ['Email',            $teacher->email ?: '—'],
                        ['Phone',            $teacher->phone ?: '—'],
                        ['Qualification',    $teacher->qualification ?: '—'],
                        ['Specialization',   $teacher->specialization ?: '—'],
                        ['Joined Date',      $teacher->joined_date?->format('d M Y') ?? '—'],
                    ] as [$label, $value])
                        <div class="px-5 py-3 flex justify-between gap-4">
                            <dt class="text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">{{ $label }}</dt>
                            <dd class="text-sm text-gray-800 text-right">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Classes assigned as class teacher --}}
            @if($teacher->schoolClasses->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Class Teacher Of</h2>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        @foreach($teacher->schoolClasses as $class)
                            <li class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-800">
                                        {{ $class->name }}{{ $class->section ? " ({$class->section})" : '' }}
                                    </p>
                                    @if($class->level)
                                        <p class="text-xs text-gray-400">{{ $class->level }}</p>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ $class->students->count() }} student{{ $class->students->count() === 1 ? '' : 's' }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>

        {{-- ── Right sidebar: subjects ──────────────────────────────────── --}}
        <div class="space-y-6">

            {{-- Quick stats --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900">At a Glance</h2>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Classes Managed</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $teacher->schoolClasses->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Subjects Teaching</span>
                        <span class="text-sm font-semibold text-gray-800">{{ $teacher->subjects->count() }}</span>
                    </div>
                    @if($teacher->joined_date)
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Years of Service</span>
                            <span class="text-sm font-semibold text-gray-800">
                                {{ (int) $teacher->joined_date->diffInYears(now()) }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- ── Teacher Portal Access ─────────────────────────────── --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ resetOpen: false }">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">Teacher Portal</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                 {{ $teacher->portal_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $teacher->portal_active ? 'Active' : 'Disabled' }}
                    </span>
                </div>
                <div class="px-5 py-4 space-y-3">

                    @if($teacher->portal_last_login)
                        <p class="text-xs text-gray-500">
                            Last login: {{ $teacher->portal_last_login->format('d M Y, H:i') }}
                        </p>
                    @else
                        <p class="text-xs text-gray-400">Never logged in.</p>
                    @endif

                    @if(! $teacher->portal_active)
                        <form method="POST" action="{{ route('admin.teachers.portal.activate', $teacher) }}"
                              class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-gray-700">Set initial password</label>
                            <input type="text" name="portal_password" required minlength="6"
                                   placeholder="Minimum 6 characters"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit"
                                    class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                                Activate Portal Access
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.teachers.portal.deactivate', $teacher) }}">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('Disable portal access for {{ addslashes($teacher->full_name) }}?')"
                                    class="w-full border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium py-2 rounded-lg transition-colors">
                                Disable Portal Access
                            </button>
                        </form>
                        <div>
                            <button @click="resetOpen = !resetOpen"
                                    class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                Reset Password
                            </button>
                            <form x-show="resetOpen" method="POST"
                                  action="{{ route('admin.teachers.portal.reset', $teacher) }}"
                                  class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="portal_password" required minlength="6"
                                       placeholder="New password (min 6 chars)"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <button type="submit"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold py-2 rounded-lg transition-colors">
                                    Set New Password
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Subjects --}}
            @if($teacher->subjects->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">Subjects</h2>
                    </div>
                    <ul class="divide-y divide-gray-50">
                        @foreach($teacher->subjects as $subject)
                            <li class="px-5 py-3">
                                <p class="text-sm text-gray-800">{{ $subject->name }}</p>
                                @if($subject->schoolClass)
                                    <p class="text-xs text-gray-400">{{ $subject->schoolClass->name }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>

    {{-- ── Footer actions ────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between pt-2">
        <a href="{{ route('admin.teachers') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Teachers
        </a>
        <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}"
              onsubmit="return confirm('Remove {{ addslashes($teacher->full_name) }}? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-1.5 text-sm text-red-500 hover:text-red-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Remove Teacher
            </button>
        </form>
    </div>

</div>
@endsection
