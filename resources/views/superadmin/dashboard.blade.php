@extends('layouts.superadmin')

@section('title', 'Super Admin Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
  $tenantCount       = \App\Models\Tenant::count();
  $activeCount       = \App\Models\Tenant::where('status', 'active')->count();
  $trialCount        = \App\Models\Tenant::where('status', 'trial')->count();
  $lockedCount       = \App\Models\Tenant::where('status', 'locked')->count();
  $revenueTotal      = \App\Models\Payment::where('status', 'success')->sum('amount');
@endphp

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
  @foreach([
    ['Total Schools',  $tenantCount,  'text-gray-900',  'bg-white'],
    ['Active',         $activeCount,  'text-emerald-700','bg-white'],
    ['On Trial',       $trialCount,   'text-blue-700',   'bg-white'],
    ['Locked',         $lockedCount,  'text-red-700',    'bg-white'],
  ] as [$label, $val, $tc, $bg])
    <div class="rounded-xl border border-gray-100 shadow-sm p-5 {{ $bg }}">
      <p class="text-xs font-medium text-gray-400 mb-1">{{ $label }}</p>
      <p class="text-3xl font-bold {{ $tc }}">{{ $val }}</p>
    </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
  {{-- Recent schools --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center">
      <h3 class="font-semibold text-gray-900">Recent Schools</h3>
      <a href="{{ route('superadmin.tenants.index') }}" class="text-xs text-blue-600 hover:underline">View all →</a>
    </div>
    <table class="w-full text-sm">
      <tbody class="divide-y divide-gray-50">
        @foreach(\App\Models\Tenant::latest()->take(8)->get() as $tenant)
          <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-800">{{ $tenant->name }}</td>
            <td class="px-4 py-2.5 text-gray-400 text-xs font-mono">{{ $tenant->slug }}</td>
            <td class="px-4 py-2.5">
              @php
                $badge = match($tenant->status) {
                  'active'    => 'bg-emerald-100 text-emerald-700',
                  'trial'     => 'bg-blue-100 text-blue-700',
                  'grace'     => 'bg-amber-100 text-amber-700',
                  'locked'    => 'bg-red-100 text-red-600',
                  default     => 'bg-gray-100 text-gray-600',
                };
              @endphp
              <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ ucfirst($tenant->status) }}</span>
            </td>
            <td class="px-4 py-2.5 text-right">
              <a href="{{ route('superadmin.tenants.show', $tenant) }}" class="text-xs text-blue-600 hover:underline">View</a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Revenue summary --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h3 class="font-semibold text-gray-900 mb-4">Revenue Overview</h3>
    <div class="text-4xl font-bold text-emerald-700 mb-1">GHS {{ number_format($revenueTotal, 2) }}</div>
    <p class="text-sm text-gray-400 mb-5">Total collected across all schools</p>

    <div class="space-y-3">
      @foreach(['success' => ['Successful','bg-emerald-500'], 'pending' => ['Pending','bg-amber-500'], 'failed' => ['Failed','bg-red-400']] as $status => [$label, $color])
        @php
          $count  = \App\Models\Payment::where('status', $status)->count();
          $amount = \App\Models\Payment::where('status', $status)->sum('amount');
        @endphp
        <div class="flex items-center justify-between text-sm">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full {{ $color }}"></span>
            <span class="text-gray-600">{{ $label }}</span>
          </div>
          <div class="text-right">
            <span class="font-semibold text-gray-800">GHS {{ number_format($amount, 2) }}</span>
            <span class="text-gray-400 text-xs ml-2">({{ $count }} txns)</span>
          </div>
        </div>
      @endforeach
    </div>

    <div class="mt-5 pt-4 border-t border-gray-100">
      <a href="{{ route('superadmin.payments') }}" class="text-sm text-blue-600 hover:underline">View all payments →</a>
    </div>
  </div>
</div>
@endsection
