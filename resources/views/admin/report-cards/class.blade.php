@extends('layouts.admin')

@section('title', 'Report Cards — ' . $class->full_name)
@section('page-title', 'Report Cards')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
  <div>
    <h2 class="text-lg font-semibold text-gray-900">{{ $class->full_name }}</h2>
    <p class="text-sm text-gray-500">{{ $term->term_name }} · {{ $term->academicYear?->year_label }} · {{ $cards->count() }} card(s)</p>
  </div>
  <a href="{{ route('admin.report-cards') }}" class="text-sm text-blue-600 hover:underline">← Change class / term</a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
@endif

{{-- ── Batch progress bar ─────────────────────────────────────────────────── --}}
@if($batch && ! $batch->finished())
  <div id="batch-progress-card" class="bg-white rounded-xl border border-blue-200 shadow-sm p-4 mb-5">
    <div class="flex items-center justify-between mb-2">
      <p class="text-sm font-semibold text-blue-700">Generating PDFs…</p>
      <span id="batch-percent" class="text-xs font-mono text-blue-600">{{ $batch->progress() }}%</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2.5">
      <div id="batch-bar"
           class="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
           style="width: {{ $batch->progress() }}%"></div>
    </div>
    <p class="text-xs text-gray-400 mt-1.5">
      <span id="batch-done">{{ $batch->processedJobs() }}</span> of
      <span id="batch-total">{{ $batch->totalJobs }}</span> done
      @if($batch->failedJobs > 0)
        · <span class="text-red-500">{{ $batch->failedJobs }} failed</span>
      @endif
    </p>
  </div>
@elseif($batch && $batch->finished())
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">
    All PDFs generated
    @if($batch->failedJobs > 0)
      · <span class="font-semibold text-red-700">{{ $batch->failedJobs }} failed</span>
    @endif
    · finished {{ $batch->finishedAt?->diffForHumans() }}.
  </div>
@endif

{{-- Bulk actions --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-5 flex flex-wrap gap-3 items-center">
  <span class="text-sm font-medium text-gray-700">Bulk Actions:</span>

  {{-- Recompute stats --}}
  <form method="POST" action="{{ route('admin.report-cards.compute') }}">
    @csrf
    <input type="hidden" name="class_id" value="{{ $class->id }}">
    <input type="hidden" name="term_id"  value="{{ $term->id }}">
    <button type="submit"
            onclick="return confirm('Recompute positions and averages for all students?')"
            class="bg-amber-100 hover:bg-amber-200 text-amber-800 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
      ↻ Recompute Statistics
    </button>
  </form>

  {{-- Generate all PDFs --}}
  <form method="POST" action="{{ route('admin.report-cards.generate-all') }}">
    @csrf
    <input type="hidden" name="class_id" value="{{ $class->id }}">
    <input type="hidden" name="term_id"  value="{{ $term->id }}">
    <button type="submit"
            @if($batch && !$batch->finished()) disabled @endif
            onclick="return confirm('Queue PDF generation for all {{ $cards->count() }} students?')"
            class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
      ⬇ Generate All PDFs
    </button>
  </form>

  <span class="text-xs text-gray-400 ml-auto">
    Tip: Recompute stats first, then generate PDFs.
  </span>
</div>

{{-- Cards table --}}
@if($cards->isEmpty())
  <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-4 text-sm">
    No report card records found. Make sure scores have been entered and statistics computed.
    <a href="{{ route('admin.assessments.index') }}" class="underline font-medium">Enter scores →</a>
  </div>
@else
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr>
          <th class="px-4 py-3 text-left font-semibold text-gray-600">Student</th>
          <th class="px-4 py-3 text-center font-semibold text-gray-600">Position</th>
          <th class="px-4 py-3 text-center font-semibold text-gray-600">Attendance</th>
          <th class="px-4 py-3 text-center font-semibold text-gray-600">PDF Status</th>
          <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50" id="cards-table-body">
        @foreach($cards as $card)
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ $card->student?->full_name }}</div>
              <div class="text-xs text-gray-400">{{ $card->student?->admission_number }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              @if($card->overall_position)
                <span class="font-bold text-blue-700">{{ $card->overall_position }}</span>
                <span class="text-gray-400 text-xs">/ {{ $card->out_of }}</span>
              @else
                <span class="text-gray-300">—</span>
              @endif
            </td>
            <td class="px-4 py-3 text-center text-sm text-gray-600">
              {{ $card->attendance_present }}/{{ $card->attendance_total }}
              <span class="text-xs text-gray-400 ml-1">({{ $card->attendance_percentage }}%)</span>
            </td>
            <td class="px-4 py-3 text-center">
              @if($card->pdf_path && $card->generated_at)
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">
                  Generated {{ $card->generated_at->diffForHumans() }}
                </span>
              @else
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                  Not generated
                </span>
              @endif
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.report-cards.remarks', $card) }}"
                   class="text-xs text-violet-600 hover:underline">Remarks</a>
                <form method="POST" action="{{ route('admin.report-cards.generate', $card) }}" class="inline">
                  @csrf
                  <button class="text-xs text-blue-600 hover:underline">Gen PDF</button>
                </form>
                @if($card->pdf_path)
                  <a href="{{ route('admin.report-cards.download', $card) }}"
                     class="text-xs text-emerald-600 hover:underline">Download</a>
                @endif
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

{{-- ── Batch progress polling script ─────────────────────────────────────── --}}
@if($batch && ! $batch->finished())
@push('scripts')
<script>
(function () {
  const batchId   = @json($batch->id);
  const statusUrl = @json(route('admin.report-cards.batch-status'));
  const bar       = document.getElementById('batch-bar');
  const pct       = document.getElementById('batch-percent');
  const done      = document.getElementById('batch-done');

  let interval = setInterval(async () => {
    try {
      const res  = await fetch(`${statusUrl}?batch_id=${encodeURIComponent(batchId)}`);
      const data = await res.json();

      bar.style.width      = data.progress + '%';
      pct.textContent      = data.progress + '%';
      done.textContent     = data.processed;

      if (data.finished || data.cancelled) {
        clearInterval(interval);
        // Reload page to show final state (download links, updated timestamps)
        setTimeout(() => window.location.reload(), 800);
      }
    } catch (e) {
      // Network hiccup — keep polling
    }
  }, 3000); // poll every 3 seconds
})();
</script>
@endpush
@endif

@endsection
