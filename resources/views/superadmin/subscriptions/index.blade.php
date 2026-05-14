@extends('layouts.superadmin')

@section('title', 'Subscriptions')
@section('page-title', 'Subscriptions')

@section('content')

{{-- Search / Filter --}}
<form method="GET" class="flex flex-wrap gap-2 mb-4">
  <input type="text" name="search" value="{{ request('search') }}" placeholder="Search school name…"
         class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 w-56">
  <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
    <option value="">All statuses</option>
    @foreach(['trial' => 'Trial', 'active' => 'Active', 'grace' => 'Grace', 'locked' => 'Locked', 'suspended' => 'Suspended'] as $val => $lbl)
      <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
    @endforeach
  </select>
  <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
    Filter
  </button>
  @if(request()->hasAny(['search', 'status']))
    <a href="{{ route('superadmin.subscriptions') }}"
       class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
      Clear
    </a>
  @endif
</form>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">School</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Term</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Trial?</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Grace Ends</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Created</th>
        <th class="px-4 py-3"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($subs as $sub)
        <tr class="hover:bg-gray-50">
          <td class="px-4 py-2.5 font-medium text-gray-800">{{ $sub->tenant?->name }}</td>
          <td class="px-4 py-2.5 text-gray-600 text-xs">
            {{ $sub->term?->term_name }} · {{ $sub->term?->academicYear?->year_label }}
          </td>
          <td class="px-4 py-2.5 text-center">
            @php $b = match($sub->status) { 'active' => 'bg-emerald-100 text-emerald-700', 'trial' => 'bg-blue-100 text-blue-700', 'grace' => 'bg-amber-100 text-amber-700', 'locked' => 'bg-red-100 text-red-600', default => 'bg-gray-100 text-gray-600' }; @endphp
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $b }}">{{ ucfirst($sub->status) }}</span>
          </td>
          <td class="px-4 py-2.5 text-center text-xs {{ $sub->is_trial ? 'text-blue-600' : 'text-gray-400' }}">
            {{ $sub->is_trial ? 'Yes' : '—' }}
          </td>
          <td class="px-4 py-2.5 text-gray-500 text-xs">
            {{ $sub->grace_ends_at?->format('d M Y') ?? '—' }}
          </td>
          <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $sub->created_at->format('d M Y') }}</td>
          <td class="px-4 py-2.5 text-right">
            <a href="{{ route('superadmin.subscriptions.show', $sub) }}"
               class="text-xs text-blue-600 hover:text-blue-800 font-medium">
              Manage →
            </a>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No subscriptions.</td></tr>
      @endforelse
    </tbody>
  </table>
  @if($subs->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $subs->links() }}</div>
  @endif
</div>
@endsection
