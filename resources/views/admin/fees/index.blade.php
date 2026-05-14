@extends('layouts.admin')

@section('title', 'Fee Management')
@section('page-title', 'Fee Management')

@section('content')

{{-- Tab links --}}
<div class="flex items-center gap-3 mb-6">
    <span class="text-sm font-semibold text-blue-600 border-b-2 border-blue-600 pb-1">All Records</span>
    <a href="{{ route('admin.fees.summary') }}"
       class="text-sm font-medium text-gray-500 hover:text-gray-700 pb-1 transition-colors">
        Class Summary →
    </a>
</div>

{{-- Summary cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  @foreach([
    ['Total Billed',   $totalDue,  'text-gray-900',   'bg-gray-50'],
    ['Total Collected', $totalPaid, 'text-emerald-700', 'bg-emerald-50'],
    ['Outstanding',    $totalOwed, 'text-red-700',     'bg-red-50'],
  ] as [$label, $value, $textColor, $bgColor])
    <div class="rounded-xl border border-gray-100 shadow-sm p-4 {{ $bgColor }}">
      <p class="text-xs font-medium text-gray-500 mb-1">{{ $label }}</p>
      <p class="text-2xl font-bold {{ $textColor }}">GHS {{ number_format($value, 2) }}</p>
    </div>
  @endforeach
</div>

{{-- Filters + Add --}}
<div class="flex flex-wrap items-end justify-between gap-3 mb-4">
  <form method="GET" class="flex flex-wrap gap-2">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Student name / admission no."
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:ring-2 focus:ring-blue-500">
    <select name="term_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All terms</option>
      @foreach($terms as $term)
        <option value="{{ $term->id }}" {{ request('term_id') == $term->id ? 'selected' : '' }}>
          {{ $term->term_name }} · {{ $term->academicYear?->year_label }}
        </option>
      @endforeach
    </select>
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All statuses</option>
      @foreach(['paid','partial','unpaid'] as $s)
        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
      @endforeach
    </select>
    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
  </form>
  <div class="flex gap-2">
    <a href="{{ route('admin.fees.export') . '?' . http_build_query(request()->only(['term_id','status'])) }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      Export CSV
    </a>
    <a href="{{ route('admin.fees.bulk') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50 transition-colors">
      Bulk Assign
    </a>
    <a href="{{ route('admin.fees.create') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
      + Record Fee
    </a>
  </div>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Student</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Term</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Fee Type</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Billed</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Paid</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Balance</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($fees as $fee)
        <tr class="hover:bg-gray-50 transition-colors" x-data="{ paying: false }">
          <td class="px-4 py-3">
            <div class="font-medium text-gray-900">{{ $fee->student?->full_name }}</div>
            <div class="text-xs text-gray-400">{{ $fee->student?->schoolClass?->full_name }}</div>
          </td>
          <td class="px-4 py-3 text-xs text-gray-600">
            {{ $fee->term?->term_name }}<br>{{ $fee->term?->academicYear?->year_label }}
          </td>
          <td class="px-4 py-3 text-gray-700">{{ $fee->fee_type }}</td>
          <td class="px-4 py-3 text-right text-gray-700">{{ number_format($fee->amount, 2) }}</td>
          <td class="px-4 py-3 text-right text-emerald-700 font-medium">{{ number_format($fee->amount_paid, 2) }}</td>
          <td class="px-4 py-3 text-right {{ $fee->balance > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
            {{ number_format($fee->balance, 2) }}
          </td>
          <td class="px-4 py-3 text-center">
            @php
              $badge = match($fee->status) {
                'paid'    => 'bg-emerald-100 text-emerald-700',
                'partial' => 'bg-amber-100 text-amber-700',
                default   => 'bg-red-100 text-red-600',
              };
            @endphp
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
              {{ ucfirst($fee->status) }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              @if($fee->status !== 'paid')
                {{-- Quick pay form --}}
                <form method="POST" action="{{ route('admin.fees.payment', $fee) }}" class="flex items-center gap-1">
                  @csrf
                  <input type="number" name="payment_amount" min="0.01" step="0.01"
                         placeholder="GHS" max="{{ $fee->balance }}"
                         class="w-20 border border-gray-200 rounded px-1.5 py-1 text-xs focus:ring-2 focus:ring-blue-400">
                  <button class="text-xs bg-emerald-500 hover:bg-emerald-600 text-white px-2 py-1 rounded transition-colors">Pay</button>
                </form>
              @endif
              <a href="{{ route('admin.fees.edit', $fee) }}" class="text-xs text-blue-600 hover:underline">Edit</a>
              <form method="POST" action="{{ route('admin.fees.destroy', $fee) }}"
                    onsubmit="return confirm('Delete this fee record?')" class="inline">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">Del</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">No fee records found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  @if($fees->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $fees->links() }}</div>
  @endif
</div>
@endsection
