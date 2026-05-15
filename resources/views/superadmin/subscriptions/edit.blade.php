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

    @php
      // Build a JS-safe map of package_id → {price, min} for Alpine auto-calc
      $pkgMap = $packages->mapWithKeys(fn ($p) => [
          $p->id => ['price' => (float)$p->price_per_student, 'min' => $p->min_students]
      ])->toJson();
    @endphp

    <form method="POST" action="{{ route('superadmin.subscriptions.update', $subscription) }}" class="space-y-4"
          x-data="{
            packageId: '{{ old('package_id', $subscription->package_id ?? '') }}',
            students:  {{ old('student_count', $studentCount) }},
            manualAmount: {{ old('amount', $subscription->amount) }},
            pkgMap: {{ $pkgMap }},
            get pkg() { return this.pkgMap[this.packageId] ?? null; },
            get calculatedAmount() {
              if (! this.pkg) return this.manualAmount;
              const billable = Math.max(this.students, this.pkg.min);
              return (billable * this.pkg.price).toFixed(2);
            }
          }">
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

      {{-- Package (new per-student pricing) --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Pricing Package</label>
        <select name="package_id" x-model="packageId"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— None / Manual Amount —</option>
          @foreach($packages as $pkg)
            <option value="{{ $pkg->id }}">
              {{ $pkg->name }} — {{ $pkg->priceLabel() }} (min {{ $pkg->min_students }} students)
            </option>
          @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Selecting a package auto-calculates the amount below.</p>
      </div>

      {{-- Student count (shown when package is selected) --}}
      <div x-show="pkg" x-cloak>
        <label class="block text-sm font-medium text-gray-700 mb-1">Active Student Count</label>
        <input type="number" name="student_count" x-model.number="students" min="0" max="99999"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-400" x-show="pkg">
          Billable: <strong x-text="Math.max(students, pkg?.min ?? 0)"></strong>
          (package minimum: <span x-text="pkg?.min"></span>)
        </p>
      </div>

      {{-- Legacy plan (kept for old records) --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Legacy Plan <span class="text-gray-400 font-normal">(old flat-rate)</span></label>
        <select name="plan_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="">— None —</option>
          @foreach($plans as $id => $plan)
            <option value="{{ $id }}" {{ old('plan_id', $subscription->plan_id) === $id ? 'selected' : '' }}>
              {{ ucfirst($id) }} — GHS {{ number_format($plan['amount'], 2) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Amount (auto-filled when package chosen, manual otherwise) --}}
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (GHS) *</label>
        <input type="number" name="amount" step="0.01" min="0" required
               :value="calculatedAmount"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-400" x-show="pkg">
          Auto-calculated from package × student count. You can still override manually.
        </p>
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
