@extends('layouts.superadmin')

@section('title', 'Schools')
@section('page-title', 'Schools')

@section('content')

<div class="flex items-center justify-between mb-6">
  <p class="text-sm text-gray-500">{{ $tenants->total() }} school(s) registered on the platform</p>
  <a href="{{ route('superadmin.tenants.create') }}"
     class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">
    + Add School
  </a>
</div>

@if(session('success'))
  <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
@endif

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">School</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Slug</th>
        <th class="px-4 py-3 text-left font-semibold text-gray-600">Email</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Status</th>
        <th class="px-4 py-3 text-center font-semibold text-gray-600">Subscriptions</th>
        <th class="px-4 py-3 text-right font-semibold text-gray-600">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($tenants as $tenant)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 font-medium text-gray-900">{{ $tenant->name }}</td>
          <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $tenant->slug }}</td>
          <td class="px-4 py-3 text-gray-600 text-xs">{{ $tenant->email }}</td>
          <td class="px-4 py-3 text-center">
            @php
              $badge = match($tenant->status) {
                'active'    => 'bg-emerald-100 text-emerald-700',
                'trial'     => 'bg-blue-100 text-blue-700',
                'grace'     => 'bg-amber-100 text-amber-700',
                'locked'    => 'bg-red-100 text-red-600',
                'suspended' => 'bg-gray-100 text-gray-600',
                default     => 'bg-gray-100 text-gray-600',
              };
            @endphp
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}">{{ ucfirst($tenant->status) }}</span>
          </td>
          <td class="px-4 py-3 text-center text-gray-600">{{ $tenant->subscriptions_count }}</td>
          <td class="px-4 py-3 text-right">
            <div class="flex items-center justify-end gap-2">
              <a href="{{ route('superadmin.tenants.show', $tenant) }}" class="text-xs text-blue-600 hover:underline">View</a>
              <a href="{{ route('superadmin.tenants.edit', $tenant) }}" class="text-xs text-gray-500 hover:text-gray-700">Edit</a>
              <form method="POST" action="{{ route('superadmin.tenants.destroy', $tenant) }}"
                    onsubmit="return confirm('Permanently delete {{ $tenant->name }}?')" class="inline">
                @csrf @method('DELETE')
                <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No schools registered yet.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  @if($tenants->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $tenants->links() }}</div>
  @endif
</div>
@endsection
