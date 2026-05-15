{{-- Shared create / edit form partial for subscription packages --}}

@if($errors->any())
  <div class="mb-5 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
    <ul class="list-disc list-inside space-y-0.5">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<div class="space-y-5">

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    {{-- Name --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Package Name *</label>
      <input type="text" name="name" value="{{ old('name', $package->name ?? '') }}" required
             placeholder="e.g. Starter, Growth, School+"
             class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <p class="mt-1 text-xs text-gray-400">The slug is auto-generated from the name.</p>
    </div>

    {{-- Billing Cycle --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle *</label>
      <select name="billing_cycle" required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
        @foreach(['term' => 'Per Term (every term)', 'annual' => 'Annual (once a year)'] as $val => $lbl)
          <option value="{{ $val }}" {{ old('billing_cycle', $package->billing_cycle ?? 'term') === $val ? 'selected' : '' }}>
            {{ $lbl }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- Price per student --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Price per Student (GHS) *</label>
      <div class="relative">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">GHS</span>
        <input type="number" name="price_per_student" step="0.01" min="0.01" max="9999"
               value="{{ old('price_per_student', $package->price_per_student ?? '') }}" required
               class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
    </div>

    {{-- Minimum students --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Students *</label>
      <input type="number" name="min_students" min="1" max="9999"
             value="{{ old('min_students', $package->min_students ?? 50) }}" required
             class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <p class="mt-1 text-xs text-gray-400">Schools below this count are billed as if they have this many students.</p>
    </div>

    {{-- Sort order --}}
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Display Order *</label>
      <input type="number" name="sort_order" min="0" max="999"
             value="{{ old('sort_order', $package->sort_order ?? 0) }}" required
             class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <p class="mt-1 text-xs text-gray-400">Lower numbers appear first on the packages list.</p>
    </div>

    {{-- Active --}}
    <div class="flex items-center gap-3 pt-6">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" id="is_active"
             {{ old('is_active', $package->is_active ?? true) ? 'checked' : '' }}
             class="rounded border-gray-300 text-blue-600">
      <label for="is_active" class="text-sm text-gray-700">Package is active (visible for assignment)</label>
    </div>

  </div>

  {{-- Description --}}
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
    <textarea name="description" rows="2" maxlength="500"
              placeholder="Optional short description shown to admins…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">{{ old('description', $package->description ?? '') }}</textarea>
  </div>

  {{-- Features --}}
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Features <span class="text-gray-400 font-normal">(one per line)</span></label>
    <textarea name="features" rows="5"
              placeholder="Students & Attendance&#10;Score Entry &amp; Report Cards&#10;Fee Management&#10;SMS Notifications&#10;Lesson Notes"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500">{{ old('features', isset($package) && $package->features ? implode("\n", $package->features) : '') }}</textarea>
    <p class="mt-1 text-xs text-gray-400">Each line becomes a bullet point on the package card.</p>
  </div>

  {{-- Live amount preview --}}
  <div class="bg-gray-50 rounded-lg p-4 border border-gray-200" x-data="{
    price: {{ $package->price_per_student ?? 0 }},
    min: {{ $package->min_students ?? 50 }},
    students: 120,
    get billable() { return Math.max(this.students, this.min); },
    get amount()   { return (this.billable * this.price).toFixed(2); }
  }">
    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Amount Preview</p>
    <div class="flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Student count to preview</label>
        <input type="number" x-model.number="students" min="1" max="9999"
               class="w-28 border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
      </div>
      <div class="text-sm text-gray-700">
        Billable: <strong x-text="billable"></strong> students
        × GHS <strong x-text="parseFloat(price || 0).toFixed(2)"></strong>
        = <strong class="text-blue-700 text-base" x-text="'GHS ' + amount"></strong>
      </div>
    </div>
    <p class="mt-2 text-xs text-gray-400">
      Update the price / min-students fields above — this preview reflects those values once you save.
    </p>
  </div>

</div>
