@extends('layouts.superadmin')

@section('title', 'SaaS Metrics')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">SaaS Revenue & Usage</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</p>
        </div>
        <div class="flex gap-2">
            @foreach(['month' => 'This Month', 'quarter' => 'This Quarter', 'year' => 'This Year'] as $key => $label)
                <a href="{{ route('superadmin.metrics', ['period' => $key]) }}"
                   class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                          {{ $period === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Headline Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @php
            $cards = [
                ['label' => 'Revenue (GHS)',    'value' => 'GHS ' . number_format($metrics['revenue'], 2),           'color' => 'blue'],
                ['label' => 'Active Schools',   'value' => number_format($metrics['activeTenants']),                   'color' => 'green'],
                ['label' => 'Grace Period',     'value' => number_format($metrics['graceTenants']),                    'color' => 'yellow'],
                ['label' => 'Locked Schools',   'value' => number_format($metrics['lockedTenants']),                   'color' => 'red'],
                ['label' => 'Total Schools',    'value' => number_format($metrics['totalTenants']),                    'color' => 'gray'],
                ['label' => 'New This Period',  'value' => number_format($metrics['newTenants']),                      'color' => 'purple'],
                ['label' => 'Active Students',  'value' => number_format($metrics['totalStudents']),                   'color' => 'indigo'],
                ['label' => 'Anomalies',        'value' => count($metrics['reconciliation']['orphaned_payments']),     'color' => 'orange'],
            ];
        @endphp
        @foreach($cards as $card)
            @php $colors = [
                'blue'   => 'bg-blue-50 text-blue-700 border-blue-200',
                'green'  => 'bg-green-50 text-green-700 border-green-200',
                'yellow' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'red'    => 'bg-red-50 text-red-700 border-red-200',
                'gray'   => 'bg-gray-50 text-gray-700 border-gray-200',
                'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
                'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'orange' => 'bg-orange-50 text-orange-700 border-orange-200',
            ]; @endphp
            <div class="rounded-xl border p-4 {{ $colors[$card['color']] ?? $colors['gray'] }}">
                <p class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold mt-1">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">

        {{-- Gateway Breakdown --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Gateway Breakdown</h2>
            @forelse($metrics['gatewayBreakdown'] as $g)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <span class="capitalize font-medium text-gray-700">{{ $g->gateway }}</span>
                    <div class="text-right">
                        <span class="text-gray-500 text-sm">{{ $g->count }} txns</span>
                        <span class="ml-3 font-semibold text-gray-900">GHS {{ number_format($g->total, 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-gray-400 text-sm">No payment data for this period.</p>
            @endforelse
        </div>

        {{-- Subscription Status --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Subscription Statuses</h2>
            @php
                $statusColors = ['active' => 'green', 'trial' => 'blue', 'grace' => 'yellow', 'locked' => 'red', 'suspended' => 'red'];
            @endphp
            @forelse($metrics['subBreakdown'] as $status => $count)
                <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-{{ $statusColors[$status] ?? 'gray' }}-500"></span>
                        <span class="capitalize text-gray-700">{{ $status }}</span>
                    </span>
                    <span class="font-semibold text-gray-900">{{ $count }}</span>
                </div>
            @empty
                <p class="text-gray-400 text-sm">No subscription data.</p>
            @endforelse
        </div>
    </div>

    {{-- Top Tenants --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-8">
        <h2 class="font-semibold text-gray-800 mb-4">Top Revenue — Schools</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-500 text-xs uppercase">
                        <th class="pb-2 pr-4">School</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 text-right">Revenue (GHS)</th>
                        <th class="pb-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($metrics['topTenants'] as $row)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="py-2 pr-4 font-medium">{{ $row->tenant?->name ?? 'Unknown' }}</td>
                            <td class="py-2 pr-4">
                                <span class="px-2 py-0.5 rounded-full text-xs
                                    {{ in_array($row->tenant?->status, ['active','trial']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $row->tenant?->status ?? '—' }}
                                </span>
                            </td>
                            <td class="py-2 text-right font-semibold">{{ number_format($row->total, 2) }}</td>
                            <td class="py-2 text-right">
                                @if($row->tenant)
                                    <a href="{{ route('superadmin.metrics.tenant', $row->tenant) }}"
                                       class="text-blue-600 hover:underline text-xs">View usage</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">No payment data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Reconciliation Anomalies --}}
    @if(count($metrics['reconciliation']['orphaned_payments']) > 0 || count($metrics['reconciliation']['duplicate_refs']) > 0)
        <div class="bg-red-50 border border-red-200 rounded-xl p-5 mb-8">
            <h2 class="font-semibold text-red-800 mb-3">⚠️ Reconciliation Anomalies</h2>

            @if(count($metrics['reconciliation']['orphaned_payments']) > 0)
                <p class="text-red-700 text-sm mb-2">
                    <strong>{{ count($metrics['reconciliation']['orphaned_payments']) }}</strong>
                    orphaned payment(s) — successful payments with no subscription linked.
                    Run <code class="bg-red-100 px-1 rounded">php artisan payments:reconcile --heal</code> to auto-fix.
                </p>
            @endif

            @if(count($metrics['reconciliation']['duplicate_refs']) > 0)
                <p class="text-red-700 text-sm">
                    <strong>{{ count($metrics['reconciliation']['duplicate_refs']) }}</strong>
                    duplicate payment reference(s) detected — possible webhook replay.
                </p>
            @endif
        </div>
    @else
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-8">
            <p class="text-green-700 text-sm">✓ No reconciliation anomalies for this period.</p>
        </div>
    @endif

</div>
@endsection
