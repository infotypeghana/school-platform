@extends('layouts.admin')

@section('title', 'Feeding Fees')
@section('page-title', 'Feeding Fees')

@section('content')

{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  <a href="{{ route('admin.feeding.index') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Fees List</a>
  <a href="{{ route('admin.feeding.config') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Configuration</a>
  <a href="{{ route('admin.feeding.assign') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Bulk Assign</a>
  <a href="{{ route('admin.feeding.report') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Report</a>
</div>

{{-- Stats strip --}}
@if($stats)
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
  @php
    $cards = [
      ['label' => 'Total',       'value' => $stats['total'],                                           'class' => 'text-gray-900'],
      ['label' => 'Paid',        'value' => $stats['paid'],                                            'class' => 'text-green-600'],
      ['label' => 'Partial',     'value' => $stats['partial'],                                         'class' => 'text-amber-600'],
      ['label' => 'Unpaid',      'value' => $stats['unpaid'],                                          'class' => 'text-red-600'],
      ['label' => 'Exempt',      'value' => $stats['exempt'],                                          'class' => 'text-purple-600'],
      ['label' => 'Collected',   'value' => 'GHS ' . number_format($stats['collected'], 2),            'class' => 'text-green-700'],
      ['label' => 'Outstanding', 'value' => 'GHS ' . number_format($stats['outstanding'], 2),          'class' => 'text-red-700'],
    ];
  @endphp
  @foreach($cards as $card)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 text-center">
      <div class="text-lg font-bold {{ $card['class'] }}">{{ $card['value'] }}</div>
      <div class="text-xs text-gray-500 mt-0.5">{{ $card['label'] }}</div>
    </div>
  @endforeach
</div>
@endif

{{-- Filters --}}
<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <select name="term_id" onchange="this.form.submit()"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    @foreach($terms as $term)
      <option value="{{ $term->id }}" {{ $selectedTermId == $term->id ? 'selected' : '' }}>
        {{ $term->term_name }} ({{ $term->academicYear?->year_label ?? '' }})
      </option>
    @endforeach
  </select>

  <select name="class_id" onchange="this.form.submit()"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All Classes</option>
    @foreach($classes as $class)
      <option value="{{ $class->id }}" {{ $selectedClassId == $class->id ? 'selected' : '' }}>
        {{ $class->name }}
      </option>
    @endforeach
  </select>

  <select name="status" onchange="this.form.submit()"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All Statuses</option>
    @foreach(['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'exempt' => 'Exempt'] as $val => $label)
      <option value="{{ $val }}" {{ $selectedStatus == $val ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>

  <a href="{{ route('admin.feeding.assign') }}"
     class="ml-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition-colors flex items-center gap-2">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    Assign Fees
  </a>
</form>

{{-- Table --}}
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="min-w-full divide-y divide-gray-100">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Due</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      @forelse($fees as $fee)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3">
            <p class="text-sm font-medium text-gray-900">{{ $fee->student?->full_name }}</p>
            <p class="text-xs text-gray-400">{{ $fee->student?->admission_number }}</p>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600">{{ $fee->schoolClass?->name }}</td>
          <td class="px-4 py-3 text-sm text-right text-gray-700">GHS {{ number_format($fee->amount_due, 2) }}</td>
          <td class="px-4 py-3 text-sm text-right text-green-700 font-medium">GHS {{ number_format($fee->amount_paid, 2) }}</td>
          <td class="px-4 py-3 text-sm text-right {{ $fee->balance() > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">
            GHS {{ number_format($fee->balance(), 2) }}
          </td>
          <td class="px-4 py-3">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $fee->statusBadgeClass() }}">
              {{ ucfirst($fee->status) }}
            </span>
          </td>
          <td class="px-4 py-3 text-right">
            <a href="{{ route('admin.feeding.show', $fee->id) }}"
               class="text-blue-600 hover:text-blue-700 text-xs font-medium">View →</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
            No feeding fees found.
            <a href="{{ route('admin.feeding.assign') }}" class="text-blue-600 hover:underline ml-1">Assign now →</a>
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  @if($fees->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">
      {{ $fees->links() }}
    </div>
  @endif
</div>

@endsection
