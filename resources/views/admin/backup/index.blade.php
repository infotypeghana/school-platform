@extends('layouts.admin')

@section('title', 'Backup & Data Export')
@section('page-title', 'Backup & Data Export')

@section('content')

{{-- Flash messages --}}
@if(session('success'))
  <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg px-4 py-3 text-sm">
    {{ session('success') }}
  </div>
@endif
@if(session('info'))
  <div class="mb-5 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg px-4 py-3 text-sm">
    {{ session('info') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    {{ session('error') }}
  </div>
@endif

<div class="max-w-3xl space-y-6">

  {{-- ── Export card ──────────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-5">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
        </div>
        <div>
          <h2 class="text-base font-bold text-white">Full School Data Export</h2>
          <p class="text-blue-200 text-xs mt-0.5">Download all your school data as a ZIP archive (CSV files)</p>
        </div>
      </div>
    </div>

    <div class="px-6 py-5">
      <p class="text-sm text-gray-600 mb-4">
        The export includes: <strong>students, teachers, classes, attendance records, assessment scores,</strong>
        and <strong>fees</strong> — all as CSV files in a single ZIP archive.
        Exports are available for download for <strong>24 hours</strong>.
      </p>

      <div class="flex flex-wrap gap-2 text-xs text-gray-500 mb-5">
        @foreach(['students.csv', 'teachers.csv', 'classes.csv', 'attendance.csv', 'assessments.csv', 'fees.csv'] as $file)
          <span class="inline-flex items-center gap-1 bg-gray-100 rounded-md px-2 py-1 font-mono">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            {{ $file }}
          </span>
        @endforeach
      </div>

      <form method="POST" action="{{ route('admin.backup.store') }}">
        @csrf
        <button type="submit"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                       font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Generate Export
        </button>
      </form>
    </div>
  </div>

  {{-- ── Export history ───────────────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">Recent Exports</h3>
      <span class="text-xs text-gray-400">Last 10 exports</span>
    </div>

    @if($exports->isEmpty())
      <div class="px-6 py-10 text-center text-gray-400 text-sm">
        No exports yet. Click "Generate Export" to create one.
      </div>
    @else
      <div class="divide-y divide-gray-50">
        @foreach($exports as $export)
          <div class="px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
              {{-- Status icon --}}
              @if($export->status === 'ready')
                <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
              @elseif($export->status === 'failed')
                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </div>
              @else
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 animate-pulse">
                  <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                  </svg>
                </div>
              @endif

              {{-- Info --}}
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-700 truncate">
                  Export #{{ $export->id }}
                  <span class="ml-1 text-xs font-normal px-1.5 py-0.5 rounded
                    {{ $export->status === 'ready' ? 'bg-emerald-100 text-emerald-700' :
                      ($export->status === 'failed' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600') }}">
                    {{ ucfirst($export->status) }}
                  </span>
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                  Requested {{ $export->created_at->diffForHumans() }}
                  @if($export->status === 'ready' && $export->expires_at)
                    &nbsp;·&nbsp;
                    @if($export->isExpired())
                      <span class="text-red-500">Expired</span>
                    @else
                      Expires {{ $export->expires_at->diffForHumans() }}
                    @endif
                  @endif
                  @if($export->status === 'failed' && $export->error_message)
                    &nbsp;·&nbsp;
                    <span class="text-red-500" title="{{ $export->error_message }}">
                      {{ Str::limit($export->error_message, 50) }}
                    </span>
                  @endif
                </p>
              </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2 flex-shrink-0">
              @if($export->isReady() && !$export->isExpired())
                <a href="{{ route('admin.backup.download', $export->id) }}"
                   class="inline-flex items-center gap-1.5 text-sm bg-blue-600 hover:bg-blue-700
                          text-white font-medium px-3 py-1.5 rounded-lg transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                  </svg>
                  Download
                </a>
              @endif

              <form method="POST" action="{{ route('admin.backup.destroy', $export->id) }}"
                    onsubmit="return confirm('Delete this export?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="text-xs text-gray-400 hover:text-red-500 transition-colors p-1.5 rounded">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  {{-- ── Notes ───────────────────────────────────────────────────────────── --}}
  <div class="bg-amber-50 border border-amber-200 rounded-lg px-5 py-4 text-sm text-amber-800">
    <strong>Note:</strong> Exports are processed in the background via the queue worker.
    If your server does not run a queue worker, exports will not process automatically.
    Run <code class="bg-amber-100 rounded px-1 font-mono text-xs">php artisan queue:work</code> or set up a daemon.
  </div>

</div>
@endsection
