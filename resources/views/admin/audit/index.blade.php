@extends('layouts.admin')

@section('title', 'Audit Trail')
@section('page-title', 'Audit Trail')

@section('content')

{{-- Filters --}}
<div class="flex flex-wrap gap-2 mb-5">
  <form method="GET" class="flex flex-wrap gap-2 flex-1">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Search record name…"
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-blue-500">

    <input type="text" name="user" value="{{ request('user') }}"
           placeholder="User name…"
           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-40 focus:ring-2 focus:ring-blue-500">

    <select name="action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All actions</option>
      @foreach($actions as $action)
        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ ucfirst($action) }}</option>
      @endforeach
    </select>

    <select name="model" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      <option value="">All models</option>
      @foreach($models as $model)
        <option value="{{ $model }}" {{ request('model') === $model ? 'selected' : '' }}>{{ $model }}</option>
      @endforeach
    </select>

    <button class="bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-lg text-sm font-medium transition-colors">Filter</button>
    @if(request()->hasAny(['search', 'user', 'action', 'model']))
      <a href="{{ route('admin.audit.index') }}" class="px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-500 hover:bg-gray-50 transition-colors">Clear</a>
    @endif
  </form>
  <p class="text-xs text-gray-400 self-center">{{ $logs->total() }} record(s)</p>
</div>

<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-gray-50 border-b border-gray-100">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Time</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">User</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Action</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Model</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Record</th>
        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">Changes</th>
        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500"></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-50">
      @forelse($logs as $log)
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
            <span title="{{ $log->created_at->format('d M Y H:i:s') }}">
              {{ $log->created_at->diffForHumans() }}
            </span>
          </td>
          <td class="px-4 py-3 text-xs text-gray-700">{{ $log->user_name ?? '—' }}</td>
          <td class="px-4 py-3">
            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $log->action_badge }}">
              {{ ucfirst($log->action) }}
            </span>
          </td>
          <td class="px-4 py-3 text-xs font-mono text-gray-500">{{ $log->model_name }}</td>
          <td class="px-4 py-3 text-xs text-gray-700 max-w-xs truncate">{{ $log->auditable_label ?? '—' }}</td>
          <td class="px-4 py-3 text-xs text-gray-400">
            @if($log->new_values)
              {{ count($log->new_values) }} field(s)
            @else
              —
            @endif
          </td>
          <td class="px-4 py-3 text-right">
            <a href="{{ route('admin.audit.show', $log) }}" class="text-xs text-blue-600 hover:underline">Details</a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">No audit logs found.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
  @if($logs->hasPages())
    <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
  @endif
</div>

@endsection
