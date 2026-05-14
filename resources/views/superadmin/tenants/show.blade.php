@extends('layouts.superadmin')

@section('title', $tenant->name)
@section('page-title', 'School Detail')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-xl font-bold text-gray-900">{{ $tenant->name }}</h2>
    <p class="text-sm text-gray-500 font-mono">{{ $tenant->slug }}</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('superadmin.tenants.edit', $tenant) }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Edit</a>
    <a href="{{ route('superadmin.tenants.index') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Back</a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  {{-- Details --}}
  <div class="space-y-4">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
      <h3 class="text-sm font-semibold text-gray-700 mb-3">School Info</h3>
      @foreach([
        ['Email',    $tenant->email],
        ['Phone',    $tenant->contact_phone ?? $tenant->phone ?? '—'],
        ['Address',  $tenant->address ?? '—'],
        ['Status',   ucfirst($tenant->status)],
        ['Created',  $tenant->created_at->format('d M Y')],
      ] as [$label, $val])
        <div class="flex justify-between py-1.5 border-b border-gray-50 text-sm">
          <span class="text-gray-400">{{ $label }}</span>
          <span class="text-gray-800 font-medium">{{ $val }}</span>
        </div>
      @endforeach
    </div>
  </div>

  {{-- Subscriptions --}}
  <div class="lg:col-span-2 space-y-5">
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Subscription History</h3>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Term</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Trial?</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Created</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($tenant->subscriptions as $sub)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2.5 text-gray-700">
                {{ $sub->term?->term_name }} · {{ $sub->term?->academicYear?->year_label }}
              </td>
              <td class="px-4 py-2.5 text-center">
                @php $b = match($sub->status) { 'active' => 'bg-emerald-100 text-emerald-700', 'trial' => 'bg-blue-100 text-blue-700', 'grace' => 'bg-amber-100 text-amber-700', 'locked' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-600' }; @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $b }}">{{ ucfirst($sub->status) }}</span>
              </td>
              <td class="px-4 py-2.5 text-center text-xs {{ $sub->is_trial ? 'text-blue-600' : 'text-gray-400' }}">
                {{ $sub->is_trial ? 'Yes' : 'No' }}
              </td>
              <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $sub->created_at->format('d M Y') }}</td>
              <td class="px-4 py-2.5 text-right">
                <a href="{{ route('superadmin.subscriptions.show', $sub) }}"
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">Manage →</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 text-sm">No subscriptions yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    {{-- Payments --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Payments</h3>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Date</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Reference</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Amount</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($tenant->payments as $payment)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $payment->created_at->format('d M Y') }}</td>
              <td class="px-4 py-2.5 font-mono text-xs text-gray-500">{{ Str::limit($payment->reference, 20) }}</td>
              <td class="px-4 py-2.5 text-right font-semibold text-gray-800">GHS {{ number_format($payment->amount, 2) }}</td>
              <td class="px-4 py-2.5 text-center">
                @php $pb = match($payment->status) { 'success' => 'bg-emerald-100 text-emerald-700', 'pending' => 'bg-amber-100 text-amber-700', default => 'bg-red-100 text-red-600' }; @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $pb }}">{{ ucfirst($payment->status) }}</span>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 text-sm">No payments yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
