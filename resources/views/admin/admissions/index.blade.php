@extends('layouts.admin')

@section('title', 'Admissions')
@section('page-title', 'Admissions')

@section('content')
<div x-data="admissionsPage()" class="space-y-6">

    {{-- ── Header bar ───────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-gray-500 mt-0.5">
                Review and manage student admission applications from your public website.
            </p>
        </div>
        {{-- Bulk action bar (visible when rows checked) --}}
        <div x-show="selected.length > 0" x-cloak class="flex items-center gap-2">
            <span class="text-sm text-gray-600 font-medium" x-text="selected.length + ' selected'"></span>
            <form method="POST" action="{{ route('admin.admissions.bulk') }}" @submit.prevent="submitBulk('accepted')">
                @csrf
                <button type="button" @click="submitBulk('accepted')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Accept Selected
                </button>
            </form>
            <button type="button" @click="submitBulk('rejected')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Reject Selected
            </button>
        </div>
    </div>

    {{-- Hidden bulk form --}}
    <form id="bulkForm" method="POST" action="{{ route('admin.admissions.bulk') }}" class="hidden">
        @csrf
        <input type="hidden" name="action" id="bulkAction">
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>

    {{-- ── Status tabs ──────────────────────────────────────────────────── --}}
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
            @foreach([
                ['key' => 'all',      'label' => 'All Applications', 'color' => 'gray'],
                ['key' => 'pending',  'label' => 'Pending',          'color' => 'amber'],
                ['key' => 'accepted', 'label' => 'Accepted',         'color' => 'emerald'],
                ['key' => 'enrolled', 'label' => 'Enrolled',         'color' => 'blue'],
                ['key' => 'rejected', 'label' => 'Rejected',         'color' => 'red'],
            ] as $tab)
                <a href="{{ route('admin.admissions.index', array_merge(request()->only(['search','term_id']), ['status' => $tab['key']])) }}"
                   class="flex items-center gap-2 whitespace-nowrap py-3 border-b-2 text-sm font-medium transition-colors
                          {{ $status === $tab['key']
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $tab['label'] }}
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] px-1.5 py-0.5 rounded-full text-xs font-semibold
                                 {{ $status === $tab['key'] ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $counts[$tab['key']] }}
                    </span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('admin.admissions.index') }}" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="status" value="{{ $status }}">

        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Name, guardian, class…"
                       class="w-full border border-gray-300 rounded-lg pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        <div class="w-52">
            <label class="block text-xs font-medium text-gray-500 mb-1">Term</label>
            <select name="term_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                <option value="">All Terms</option>
                @foreach($terms as $term)
                    <option value="{{ $term->id }}" @selected($termId == $term->id)>
                        {{ $term->term_name }} ({{ $term->academicYear?->year_label ?? '—' }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                Filter
            </button>
            @if($search || $termId || $status !== 'all')
                <a href="{{ route('admin.admissions.index') }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- ── Table ─────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        @if($admissions->isEmpty())
            <div class="py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm text-gray-500">No admissions found.</p>
                @if($search || $termId)
                    <a href="{{ route('admin.admissions.index', ['status' => $status]) }}"
                       class="mt-2 inline-block text-sm text-blue-600 hover:underline">Clear filters</a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox"
                                       @change="toggleAll($event)"
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Applicant</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Class Applying</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Guardian</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Term</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Submitted</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($admissions as $admission)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <input type="checkbox"
                                       :value="{{ $admission->id }}"
                                       x-model="selected"
                                       @if($admission->status !== 'pending') disabled @endif
                                       class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-40">
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-8 w-8 rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold
                                                {{ $admission->gender === 'male' ? 'bg-blue-100 text-blue-700' : 'bg-pink-100 text-pink-700' }}">
                                        {{ strtoupper(substr($admission->first_name, 0, 1) . substr($admission->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $admission->full_name }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ ucfirst($admission->gender) }} ·
                                            DOB: {{ $admission->date_of_birth?->format('d M Y') ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 font-medium">
                                {{ $admission->class_applying_for ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-gray-800">{{ $admission->guardian_name }}</p>
                                <p class="text-xs text-gray-400">{{ $admission->guardian_phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">
                                {{ $admission->term?->term_name ?? 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                {{ $admission->submitted_at?->format('d M Y H:i') ?? $admission->created_at->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $badge = match($admission->status) {
                                        'accepted' => 'bg-emerald-100 text-emerald-700',
                                        'rejected' => 'bg-red-100 text-red-700',
                                        default    => 'bg-amber-100 text-amber-700',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                    {{ ucfirst($admission->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.admissions.show', $admission) }}"
                                       class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    @if($admission->status === 'pending')
                                        <form method="POST" action="{{ route('admin.admissions.accept', $admission) }}">
                                            @csrf
                                            <button type="submit" title="Accept"
                                                    class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                        </form>
                                        <button type="button" title="Reject"
                                                @click="openReject({{ $admission->id }}, '{{ route('admin.admissions.reject', $admission) }}'.replace('/reject', ''))"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    @endif
                                    <form method="POST" action="{{ route('admin.admissions.destroy', $admission) }}"
                                          onsubmit="return confirm('Delete this application permanently?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Delete"
                                                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($admissions->hasPages())
                <div class="px-6 py-4 border-t border-gray-100">
                    {{ $admissions->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- ══ Reject Modal ════════════════════════════════════════════════════ --}}
    <div x-show="rejectModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="rejectModal = false">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="rejectModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 space-y-4"
             @click.stop>
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Reject Application</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Optionally provide a reason (internal only).</p>
                </div>
                <button type="button" @click="rejectModal = false"
                        class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form :action="rejectBaseUrl + '/reject'" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rejection Reason (optional)</label>
                        <textarea name="rejection_reason" rows="3"
                                  placeholder="e.g. Class is full, age requirement not met…"
                                  class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button type="submit"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors">
                            Confirm Rejection
                        </button>
                        <button type="button" @click="rejectModal = false"
                                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2.5 rounded-xl text-sm transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function admissionsPage() {
    return {
        selected: [],
        rejectModal: false,
        rejectId: null,
        rejectBaseUrl: '',

        toggleAll(event) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][x-model="selected"]:not([disabled])');
            if (event.target.checked) {
                this.selected = Array.from(checkboxes).map(cb => parseInt(cb.value));
            } else {
                this.selected = [];
            }
        },

        openReject(id, baseUrl) {
            this.rejectId = id;
            this.rejectBaseUrl = baseUrl;
            this.rejectModal = true;
        },

        submitBulk(action) {
            if (this.selected.length === 0) return;
            const label = action === 'accepted' ? 'accept' : 'reject';
            if (!confirm(`Are you sure you want to ${label} ${this.selected.length} application(s)?`)) return;

            const form = document.getElementById('bulkForm');
            document.getElementById('bulkAction').value = action;

            // Remove old id inputs
            form.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());

            // Add selected ids
            this.selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });

            form.submit();
        }
    }
}
</script>
@endpush
