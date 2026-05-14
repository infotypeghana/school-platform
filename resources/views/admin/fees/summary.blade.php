@extends('layouts.admin')

@section('title', 'Fee Summary')
@section('page-title', 'Fee Summary')

@section('content')
<div class="space-y-6">

    {{-- ── Header + term filter ────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-gray-500">
                Collection overview by class
                @if($term)
                    — <span class="font-medium text-gray-700">{{ $term->term_name }} {{ $term->academicYear?->year_label }}</span>
                @endif
            </p>
        </div>
        <form method="GET" class="flex items-end gap-2">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Academic Term</label>
                <select name="term_id" onchange="this.form.submit()"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">All Terms</option>
                    @foreach($terms as $t)
                        <option value="{{ $t->id }}" {{ $termId == $t->id ? 'selected' : '' }}>
                            {{ $t->term_name }} ({{ $t->academicYear?->year_label ?? '—' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('admin.fees') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 bg-white
                      text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                View All Records
            </a>
        </form>
    </div>

    {{-- ── Grand totals ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        @php $rate = $grandTotal > 0 ? round(($grandPaid / $grandTotal) * 100, 1) : 0; @endphp

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 mb-1">Total Levied</p>
            <p class="text-2xl font-bold text-gray-900">GHS {{ number_format($grandTotal, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 mb-1">Total Collected</p>
            <p class="text-2xl font-bold text-emerald-600">GHS {{ number_format($grandPaid, 2) }}</p>
            <div class="mt-2">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>Collection rate</span><span>{{ $rate }}%</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $rate }}%"></div>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-medium text-gray-500 mb-1">Outstanding Balance</p>
            <p class="text-2xl font-bold {{ $grandBalance > 0 ? 'text-red-600' : 'text-gray-400' }}">
                GHS {{ number_format($grandBalance, 2) }}
            </p>
        </div>
    </div>

    {{-- ── Per-class breakdown ───────────────────────────────────────────────── --}}
    @if(count($summary) > 0)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Breakdown by Class</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600">Class</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600">Records</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600">Levied (GHS)</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600">Collected (GHS)</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600">Balance (GHS)</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold text-gray-600">Status Split</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($summary as $row)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">
                                        {{ $row['class']->name }}{{ $row['class']->section ? " ({$row['class']->section})" : '' }}
                                    </p>
                                    @if($row['class']->level)
                                        <p class="text-xs text-gray-400">{{ $row['class']->level }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right text-gray-600">{{ $row['fee_count'] }}</td>
                                <td class="px-5 py-3 text-right font-medium text-gray-800">
                                    {{ number_format($row['total_levied'], 2) }}
                                </td>
                                <td class="px-5 py-3 text-right font-medium text-emerald-700">
                                    {{ number_format($row['total_paid'], 2) }}
                                </td>
                                <td class="px-5 py-3 text-right font-medium {{ $row['total_balance'] > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                    {{ number_format($row['total_balance'], 2) }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-center gap-2 text-xs">
                                        @if($row['paid_count'] > 0)
                                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full font-medium">
                                                {{ $row['paid_count'] }} paid
                                            </span>
                                        @endif
                                        @if($row['partial_count'] > 0)
                                            <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                                {{ $row['partial_count'] }} partial
                                            </span>
                                        @endif
                                        @if($row['unpaid_count'] > 0)
                                            <span class="inline-flex items-center gap-1 bg-red-50 text-red-700 px-2 py-0.5 rounded-full font-medium">
                                                {{ $row['unpaid_count'] }} unpaid
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @php $r = $row['collection_rate']; @endphp
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full {{ $r >= 75 ? 'bg-emerald-500' : ($r >= 40 ? 'bg-amber-400' : 'bg-red-400') }}"
                                                 style="width: {{ $r }}%"></div>
                                        </div>
                                        <span class="text-xs font-semibold {{ $r >= 75 ? 'text-emerald-600' : ($r >= 40 ? 'text-amber-600' : 'text-red-600') }}">
                                            {{ $r }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- Grand total row --}}
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-5 py-3 font-semibold text-gray-700 text-sm">Total</td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right font-bold text-gray-900">{{ number_format($grandTotal, 2) }}</td>
                            <td class="px-5 py-3 text-right font-bold text-emerald-700">{{ number_format($grandPaid, 2) }}</td>
                            <td class="px-5 py-3 text-right font-bold {{ $grandBalance > 0 ? 'text-red-600' : 'text-gray-400' }}">
                                {{ number_format($grandBalance, 2) }}
                            </td>
                            <td class="px-5 py-3"></td>
                            <td class="px-5 py-3 text-right font-bold text-sm {{ $rate >= 75 ? 'text-emerald-600' : ($rate >= 40 ? 'text-amber-600' : 'text-red-600') }}">
                                {{ $rate }}%
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-6 py-16 text-center">
            <svg class="mx-auto w-12 h-12 text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                         a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-500 text-sm">No fee records found for the selected term.</p>
            <a href="{{ route('admin.fees.create') }}"
               class="mt-4 inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline">
                Create fee records
            </a>
        </div>
    @endif

</div>
@endsection
