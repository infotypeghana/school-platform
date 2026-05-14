@extends('layouts.superadmin')

@section('title', 'Edit Subscription')
@section('page-title', 'Edit Subscription')

@section('content')

<div class="flex items-center gap-3 mb-6">
  <a href="{{ route('superadmin.subscriptions.show', $subscription) }}"
     class="text-gray-400 hover:text-gray-600 transition-colors text-sm">← Back</a>
  <span class="text-gray-300">/</span>
  <span class="text-sm text-gray-600 font-medium">
    {{ $subscription->tenant?->name }} — {{ $subscription->term?->term_name }}
  </span>
</div>

<div class="max-w-lg">
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">

    @if($errors->any())
      <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        <ul class="list-disc list-inside space-y-0.5">
          @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form method="POST" action="{{ route('superadmin.subscriptions.update', $subscription) }}" class="space-y-4">
      @csrf @method('PUT')

      {{-- Status --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
        <select name="status" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          @foreach(['trial' => 'Trial', 'active' => 'Active', 'grace' => 'Grace', 'locked' => 'Locked', 'suspended' => 'Suspended'] as $val => $lbl)
            <option value="{{ $val }}" {{ old('status', $subscription->status) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>

      {{-- Plan --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
        <select name="plan_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— None / Custom —</option>
          @foreach($plans as $id => $plan)
            <option value="{{ $id }}" {{ old('plan_id', $subscription->plan_id) === $id ? 'selected' : '' }}>
              {{ ucfirst($id) }} — GHS {{ number_format($plan['amount'], 2) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Amount --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (GHS) *</label>
        <input type="number" name="amount" step="0.01" min="0"
               value="{{ old('amount', $subscription->amount) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>

      {{-- End Date --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
        <input type="date" name="end_date"
               value="{{ old('end_date', $subscription->end_date?->format('Y-m-d')) }}" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>

      {{-- Grace Ends At --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Grace Ends At</label>
        <input type="datetime-local" name="grace_ends_at"
               value="{{ old('grace_ends_at', $subscription->grace_ends_at?->format('Y-m-d\TH:i')) }}"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-400">Leave blank to clear. Only meaningful in grace status.</p>
      </div>

      {{-- Is Trial --}}
      <div class="flex items-center gap-3">
        <input type="hidden" name="is_trial" value="0">
        <input type="checkbox" name="is_trial" value="1" id="is_trial"
               {{ old('is_trial', $subscription->is_trial) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600">
        <label for="is_trial" class="text-sm text-gray-700">Mark as trial subscription</label>
      </div>

      {{-- Actions --}}
      <div class="flex gap-3 pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm">
          Save Changes
        </button>
        <a href="{{ route('superadmin.subscriptions.show', $subscription) }}"
           class="px-6 py-2.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancel
        </a>
      </div>
    </form>
  </div>
</div>
@endsection
