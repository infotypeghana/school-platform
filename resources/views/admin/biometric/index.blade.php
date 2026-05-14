@extends('layouts.admin')

@section('title', 'Biometric Attendance')
@section('page-title', 'Biometric Attendance — ZKTeco')

@section('content')

{{-- KPI bar --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
  @foreach([
    ['Devices Active',   $stats['devices_active'], 'text-blue-700',    'bg-blue-50'],
    ['Today\'s Punches', $stats['today'],           'text-emerald-700', 'bg-emerald-50'],
    ['Total Punches',    $stats['total_punches'],   'text-gray-800',    'bg-gray-50'],
    ['Unmatched',        $stats['unmatched'],       $stats['unmatched'] > 0 ? 'text-amber-700' : 'text-gray-400', $stats['unmatched'] > 0 ? 'bg-amber-50' : 'bg-gray-50'],
  ] as [$label, $val, $color, $bg])
    <div class="rounded-xl border border-gray-100 shadow-sm p-4 {{ $bg }}">
      <p class="text-xs font-medium text-gray-500 mb-1">{{ $label }}</p>
      <p class="text-2xl font-bold {{ $color }}">{{ number_format($val) }}</p>
    </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  {{-- Device cards --}}
  <div class="lg:col-span-1 space-y-3">
    <div class="flex items-center justify-between mb-1">
      <h3 class="text-sm font-semibold text-gray-700">Registered Devices</h3>
      <a href="{{ route('admin.biometric.create') }}"
         class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg transition-colors">
        + Add Device
      </a>
    </div>

    @forelse($devices as $device)
      <div class="bg-white rounded-xl border {{ $device->is_active ? 'border-gray-100' : 'border-gray-200' }} shadow-sm p-4">
        <div class="flex items-start justify-between gap-2">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-gray-900">{{ $device->name }}</span>
              @if($device->is_active)
                <span class="inline-flex w-2 h-2 rounded-full bg-emerald-400"></span>
              @else
                <span class="inline-flex w-2 h-2 rounded-full bg-gray-300"></span>
              @endif
            </div>
            @if($device->model)
              <p class="text-xs text-gray-500 mt-0.5">{{ $device->model }}@if($device->location) · {{ $device->location }}@endif</p>
            @endif
            @if($device->device_serial)
              <p class="text-xs font-mono text-gray-400 mt-0.5">SN: {{ $device->device_serial }}</p>
            @endif
            @if($device->ip_address)
              <p class="text-xs font-mono text-gray-400">{{ $device->ip_address }}:{{ $device->port }}</p>
            @endif
            <div class="flex gap-3 mt-1 text-xs text-gray-400">
              <span>{{ $device->logs_count }} punches</span>
              <span>{{ $device->enrollments_count }} enrolled</span>
            </div>
            @if($device->last_sync_at)
              <p class="text-xs text-gray-400 mt-0.5">Synced {{ $device->last_sync_at->diffForHumans() }}</p>
            @else
              <p class="text-xs text-amber-500 mt-0.5">Never synced</p>
            @endif
          </div>
        </div>

        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-50">
          @if($device->ip_address)
            <form method="POST" action="{{ route('admin.biometric.sync', $device) }}">
              @csrf
              <button class="text-xs bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg transition-colors font-medium">
                ↻ Sync TCP
              </button>
            </form>
          @endif
          <a href="{{ route('admin.biometric.enroll', $device) }}"
             class="text-xs border border-gray-200 px-3 py-1.5 rounded-lg text-gray-600 hover:bg-gray-50 transition-colors">
            Enroll Users
          </a>
          <a href="{{ route('admin.biometric.edit', $device) }}"
             class="text-xs text-blue-600 hover:underline px-1 py-1.5">Edit</a>
          <form method="POST" action="{{ route('admin.biometric.destroy', $device) }}"
                onsubmit="return confirm('Remove this device and all its logs?')" class="inline">
            @csrf @method('DELETE')
            <button class="text-xs text-red-400 hover:text-red-600 px-1 py-1.5">Delete</button>
          </form>
        </div>
      </div>
    @empty
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-10 text-center">
        <p class="text-sm text-gray-400 mb-3">No devices registered yet.</p>
        <a href="{{ route('admin.biometric.create') }}"
           class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors font-semibold">
          + Register ZKTeco Device
        </a>
      </div>
    @endforelse

    {{-- ADMS setup guide --}}
    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-xs text-blue-800">
      <p class="font-semibold mb-2">📡 ADMS Setup (Recommended)</p>
      <p class="mb-1">On the ZKTeco device, navigate to <strong>Comm → Cloud Server</strong> and set:</p>
      <ul class="list-disc list-inside space-y-0.5 text-blue-700">
        <li>Server Address: <code class="bg-blue-100 px-1 rounded">{{ parse_url(config('app.url'), PHP_URL_HOST) }}</code></li>
        <li>Port: <code class="bg-blue-100 px-1 rounded">80</code></li>
        <li>Enable ADMS: <strong>ON</strong></li>
      </ul>
      <p class="mt-2">The device will push attendance to:<br>
        <code class="bg-blue-100 px-1 rounded break-all">{{ config('app.url') }}/biometric/adms</code></p>
    </div>
  </div>

  {{-- Live punch feed --}}
  <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">Live Punch Feed</h3>
      <span class="text-xs text-gray-400" id="feed-refresh">Auto-refreshes every 30s</span>
    </div>
    <div id="punch-feed" class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">User ID</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Device</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Type</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Direction</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">Time</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
          </tr>
        </thead>
        <tbody id="feed-body" class="divide-y divide-gray-50">
          @forelse($recentLogs as $log)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 font-mono text-xs text-gray-700 font-medium">{{ $log->device_user_id }}</td>
              <td class="px-4 py-2 text-xs text-gray-500">{{ $log->device?->name ?? '—' }}</td>
              <td class="px-4 py-2 text-center">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                  {{ $log->verify_type === 15 ? 'bg-purple-100 text-purple-700' : ($log->verify_type === 4 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                  {{ $log->verify_label }}
                </span>
              </td>
              <td class="px-4 py-2 text-center text-xs text-gray-600">{{ $log->direction_label }}</td>
              <td class="px-4 py-2 text-right text-xs text-gray-500">
                {{ $log->verified_at->format('d M') }}<br>
                <span class="font-mono">{{ $log->verified_at->format('H:i:s') }}</span>
              </td>
              <td class="px-4 py-2 text-center">
                @if($log->is_processed)
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700">Matched</span>
                @else
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Unmatched</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">No punch records yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@endsection

@push('scripts')
<script>
// Auto-refresh the live feed every 30 seconds via JSON
setInterval(async () => {
  try {
    const resp = await fetch('{{ route("admin.biometric.recent") }}');
    const logs = await resp.json();
    const tbody = document.getElementById('feed-body');
    if (!tbody || logs.length === 0) return;

    const verifyColor = (t) => t === 15 ? 'bg-purple-100 text-purple-700' : t === 4 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600';

    tbody.innerHTML = logs.map(l => `
      <tr class="hover:bg-gray-50">
        <td class="px-4 py-2 font-mono text-xs text-gray-700 font-medium">${l.user_id}</td>
        <td class="px-4 py-2 text-xs text-gray-500">${l.device ?? '—'}</td>
        <td class="px-4 py-2 text-center">
          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${verifyColor(l.verify_type)}">${l.verify_type}</span>
        </td>
        <td class="px-4 py-2 text-center text-xs text-gray-600">${l.direction}</td>
        <td class="px-4 py-2 text-right text-xs text-gray-500">${l.date}<br><span class="font-mono">${l.time}</span></td>
        <td class="px-4 py-2 text-center">
          <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${l.processed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">
            ${l.processed ? 'Matched' : 'Unmatched'}
          </span>
        </td>
      </tr>
    `).join('');
  } catch(e) { /* silent fail */ }
}, 30000);
</script>
@endpush
