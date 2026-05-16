@extends('layouts.superadmin')

@section('title', $tenant->name . ' — Usage')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('superadmin.metrics') }}" class="text-gray-400 hover:text-gray-600">← Metrics</a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $tenant->name }}</h1>
        <span class="px-2 py-0.5 rounded-full text-xs
            {{ in_array($tenant->status, ['active','trial']) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
            {{ $tenant->status }}
        </span>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p class="text-xs text-blue-600 font-medium uppercase">Active Students</p>
            <p class="text-2xl font-bold text-blue-800 mt-1">{{ number_format($usage['studentCount']) }}</p>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-xl p-4">
            <p class="text-xs text-green-600 font-medium uppercase">Total Paid (GHS)</p>
            <p class="text-2xl font-bold text-green-800 mt-1">{{ number_format($usage['totalPayments'], 2) }}</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
            <p class="text-xs text-gray-500 font-medium uppercase">Last Payment</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">
                {{ $usage['lastPayment']?->created_at?->format('d M Y') ?? 'Never' }}
            </p>
        </div>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
            <p class="text-xs text-purple-600 font-medium uppercase">Subscription</p>
            <p class="text-sm font-semibold text-purple-800 mt-1 capitalize">
                {{ $usage['currentSub']?->package?->name ?? $usage['currentSub']?->status ?? 'None' }}
            </p>
        </div>
    </div>

    {{-- Recent Audit Activity --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h2 class="font-semibold text-gray-800 mb-4">Recent Activity (Audit Log)</h2>
        <div class="space-y-2">
            @forelse($usage['recentActivity'] as $log)
                <div class="flex items-start justify-between text-sm py-2 border-b border-gray-50 last:border-0">
                    <div>
                        <span class="font-medium text-gray-800">{{ $log->action ?? 'unknown' }}</span>
                        @if(isset($log->user_id))
                            <span class="ml-2 text-gray-400 text-xs">by user #{{ $log->user_id }}</span>
                        @endif
                    </div>
                    <span class="text-gray-400 text-xs whitespace-nowrap ml-4">
                        {{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->diffForHumans() : '—' }}
                    </span>
                </div>
            @empty
                <p class="text-gray-400 text-sm">No recent activity recorded.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4 flex gap-3">
        <a href="{{ route('superadmin.tenants.show', $tenant) }}"
           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-700">
            View Tenant Profile
        </a>
        <a href="{{ route('superadmin.subscriptions', ['tenant' => $tenant->id]) }}"
           class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium text-white">
            View Subscriptions
        </a>
    </div>

</div>
@endsection
