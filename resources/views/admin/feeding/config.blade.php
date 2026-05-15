@extends('layouts.admin')

@section('title', 'Feeding Fee Configuration')
@section('page-title', 'Feeding Fees')

@section('content')

{{-- Sub-navigation --}}
<div class="flex gap-1 mb-6 border-b border-gray-200 -mt-2 flex-wrap">
  <a href="{{ route('admin.feeding.index') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Fees List</a>
  <a href="{{ route('admin.feeding.config') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Configuration</a>
  <a href="{{ route('admin.feeding.assign') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Bulk Assign</a>
  <a href="{{ route('admin.feeding.report') }}"
     class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">Report</a>
</div>

<div class="max-w-3xl space-y-8">

  {{-- ── School-Wide Default ──────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-1">School-Wide Default Rate</h2>
    <p class="text-sm text-gray-500 mb-5">Applies to all classes unless a per-class override is set.</p>

    <form method="POST" action="{{ route('admin.feeding.config.update') }}" class="space-y-4">
      @csrf @method('PUT')

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Daily Rate (GHS) *</label>
          <input type="number" name="rate_per_day" step="0.01" min="0" required
                 value="{{ old('rate_per_day', $schoolWide?->rate_per_day ?? '') }}"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                 placeholder="e.g. 5.00">
          <p class="mt-1 text-xs text-gray-400">Cost per student per school day</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Billing Mode *</label>
          <select name="billing_mode"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'termly' => 'Termly (full term)'] as $val => $label)
              <option value="{{ $val }}" {{ old('billing_mode', $schoolWide?->billing_mode) === $val ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">School Days per Week</label>
          <select name="school_days_per_week"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            @for($d = 1; $d <= 7; $d++)
              <option value="{{ $d }}" {{ old('school_days_per_week', $schoolWide?->school_days_per_week ?? 5) == $d ? 'selected' : '' }}>
                {{ $d }} {{ $d === 1 ? 'day' : 'days' }}
              </option>
            @endfor
          </select>
        </div>
        <div class="flex items-center pt-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $schoolWide?->is_active ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
        <textarea name="notes" rows="2"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                  placeholder="Optional notes about this rate...">{{ old('notes', $schoolWide?->notes) }}</textarea>
      </div>

      <div class="pt-2 border-t border-gray-100">
        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors">
          Save School-Wide Rate
        </button>
      </div>
    </form>
  </div>

  {{-- ── Per-Class Overrides ──────────────────────────────────────────── --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
    <h2 class="text-base font-semibold text-gray-900 mb-1">Per-Class Overrides</h2>
    <p class="text-sm text-gray-500 mb-5">
      Set a different rate for specific classes. Leave blank to inherit the school-wide rate.
    </p>

    @foreach($classes as $class)
      @php $classConfig = $classConfigs[$class->id] ?? null; @endphp
      <details class="border border-gray-200 rounded-lg mb-3" {{ $classConfig ? 'open' : '' }}>
        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer select-none">
          <div class="flex items-center gap-2">
            <span class="font-medium text-sm text-gray-900">{{ $class->name }}</span>
            @if($classConfig)
              <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Override set</span>
            @else
              <span class="text-xs text-gray-400">Using school-wide rate</span>
            @endif
          </div>
          <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </summary>

        <div class="px-4 pb-4 border-t border-gray-100 pt-3">
          <form method="POST" action="{{ route('admin.feeding.config.class', $class->id) }}" class="space-y-3">
            @csrf @method('PUT')

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Daily Rate (GHS)</label>
                <input type="number" name="rate_per_day" step="0.01" min="0"
                       value="{{ old("rate_per_day_{$class->id}", $classConfig?->rate_per_day ?? $schoolWide?->rate_per_day ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Billing Mode</label>
                <select name="billing_mode"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                  @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'termly' => 'Termly'] as $val => $lbl)
                    <option value="{{ $val }}" {{ ($classConfig?->billing_mode ?? $schoolWide?->billing_mode ?? 'termly') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Days/week</label>
                <select name="school_days_per_week"
                        class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                  @for($d = 1; $d <= 7; $d++)
                    <option value="{{ $d }}" {{ ($classConfig?->school_days_per_week ?? $schoolWide?->school_days_per_week ?? 5) == $d ? 'selected' : '' }}>{{ $d }}</option>
                  @endfor
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                <input type="text" name="notes" value="{{ $classConfig?->notes }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500"
                       placeholder="Optional">
              </div>
            </div>

            <input type="hidden" name="is_active" value="1">

            <div class="flex gap-2">
              <button type="submit"
                      class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-4 py-1.5 rounded-lg text-xs transition-colors">
                Save Override
              </button>
              @if($classConfig)
                <button type="submit" name="_remove" value="1"
                        class="text-red-600 hover:text-red-700 font-medium px-3 py-1.5 text-xs rounded-lg border border-red-200 hover:bg-red-50 transition-colors">
                  Remove Override
                </button>
              @endif
            </div>
          </form>
        </div>
      </details>
    @endforeach

    @if($classes->isEmpty())
      <p class="text-sm text-gray-400 text-center py-4">No classes found. Create classes first.</p>
    @endif
  </div>

</div>

@endsection
