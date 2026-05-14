@extends('layouts.admin')

@section('title', 'Audit Log Detail')
@section('page-title', 'Audit Log Detail')

@section('content')

<div class="max-w-3xl">

  <div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.audit.index') }}"
       class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition-colors">← Audit Trail</a>
    <span class="inline-flex px-2.5 py-1 rounded-full text-sm font-semibold {{ $auditLog->action_badge }}">
      {{ ucfirst($auditLog->action) }}
    </span>
  </div>

  {{-- Header card --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
    <div class="grid grid-cols-2 gap-4 text-sm">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Timestamp</p>
        <p class="font-medium text-gray-800">{{ $auditLog->created_at->format('d M Y, H:i:s') }}</p>
        <p class="text-xs text-gray-400">{{ $auditLog->created_at->diffForHumans() }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">User</p>
        <p class="font-medium text-gray-800">{{ $auditLog->user_name ?? 'System' }}</p>
        @if($auditLog->ip_address)
          <p class="text-xs text-gray-400 font-mono">{{ $auditLog->ip_address }}</p>
        @endif
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Model</p>
        <p class="font-medium text-gray-700 font-mono text-xs">{{ $auditLog->auditable_type ?? '—' }}</p>
        <p class="text-xs text-gray-400">ID: {{ $auditLog->auditable_id ?? '—' }}</p>
      </div>
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Record</p>
        <p class="font-medium text-gray-800">{{ $auditLog->auditable_label ?? '—' }}</p>
      </div>
    </div>
  </div>

  {{-- Diff --}}
  @if($auditLog->old_values || $auditLog->new_values)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50">
        <h3 class="text-sm font-semibold text-gray-700">Changes</h3>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-1/4">Field</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-5/12">Before</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 w-5/12">After</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @php
            $keys = array_unique(array_merge(
              array_keys($auditLog->old_values ?? []),
              array_keys($auditLog->new_values ?? [])
            ));
          @endphp
          @foreach($keys as $key)
            <tr>
              <td class="px-4 py-2 font-mono text-xs text-gray-500">{{ $key }}</td>
              <td class="px-4 py-2 text-xs text-red-600 break-all">
                @php $old = $auditLog->old_values[$key] ?? null; @endphp
                {{ is_array($old) ? json_encode($old) : ($old ?? '—') }}
              </td>
              <td class="px-4 py-2 text-xs text-emerald-700 break-all">
                @php $new = $auditLog->new_values[$key] ?? null; @endphp
                {{ is_array($new) ? json_encode($new) : ($new ?? '—') }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 text-sm text-gray-400 text-center">
      No field-level diff recorded for this event.
    </div>
  @endif

</div>
@endsection
