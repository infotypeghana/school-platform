@extends('layouts.admin')

@section('title', 'SMS & WhatsApp')
@section('page-title', 'SMS & WhatsApp Notifications')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
  @foreach([
    ['Total Sent',    $stats['total'],  'bg-gray-50',    'text-gray-800'],
    ['Delivered',     $stats['sent'],   'bg-emerald-50', 'text-emerald-700'],
    ['Failed',        $stats['failed'], 'bg-red-50',     'text-red-700'],
    ['SMS',           $stats['sms'],    'bg-blue-50',    'text-blue-700'],
    ['WhatsApp',      $stats['wa'],     'bg-green-50',   'text-green-700'],
  ] as [$label, $val, $bg, $color])
    <div class="rounded-xl border border-gray-100 shadow-sm p-4 {{ $bg }}">
      <p class="text-xs font-medium text-gray-500 mb-1">{{ $label }}</p>
      <p class="text-2xl font-bold {{ $color }}">{{ number_format($val) }}</p>
    </div>
  @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

  {{-- Test / manual send --}}
  <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">Send a Test Message</h3>
    <form method="POST" action="{{ route('admin.sms.send') }}" class="space-y-3">
      @csrf
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Recipient Phone *</label>
        <input type="text" name="recipient" value="{{ old('recipient') }}" placeholder="0241234567"
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Channel *</label>
        <select name="channel" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
          <option value="sms">SMS</option>
          <option value="whatsapp">WhatsApp</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Message * <span class="text-gray-400">(max 160)</span></label>
        <textarea name="message" rows="3" maxlength="160"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                  placeholder="Type your message…">{{ old('message') }}</textarea>
      </div>
      @if($errors->any())
        <p class="text-xs text-red-600">{{ $errors->first() }}</p>
      @endif
      <button type="submit"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
        Send Message
      </button>
    </form>

    <div class="mt-5 pt-4 border-t border-gray-100">
      <p class="text-xs font-semibold text-gray-600 mb-2">Automatic triggers</p>
      <ul class="text-xs text-gray-500 space-y-1 list-disc list-inside">
        <li>Fee payment recorded → guardian SMS</li>
        <li>Student marked absent → guardian SMS</li>
      </ul>
      <p class="text-xs text-gray-400 mt-2">
        Powered by Hubtel. Configure credentials in your <code>.env</code> file:<br>
        <code class="text-blue-600">HUBTEL_CLIENT_ID</code>, <code class="text-blue-600">HUBTEL_CLIENT_SECRET</code>,
        <code class="text-blue-600">HUBTEL_SENDER_ID</code>
      </p>
    </div>
  </div>

  {{-- Log --}}
  <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
      <h3 class="text-sm font-semibold text-gray-700">Message Log</h3>
      <form method="GET" class="flex gap-2">
        <select name="channel" class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500">
          <option value="">All channels</option>
          <option value="sms" {{ request('channel') === 'sms' ? 'selected' : '' }}>SMS</option>
          <option value="whatsapp" {{ request('channel') === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
        </select>
        <select name="status" class="border border-gray-200 rounded-lg px-2 py-1 text-xs focus:ring-2 focus:ring-blue-500">
          <option value="">All statuses</option>
          @foreach(['sent','failed','pending'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
        <button class="bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded-lg text-xs transition-colors">Filter</button>
      </form>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">To</th>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500">Message</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Channel</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-500">Status</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500">When</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          @forelse($logs as $log)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 font-mono text-xs text-gray-600">{{ $log->recipient }}</td>
              <td class="px-4 py-2 text-gray-700 text-xs max-w-xs truncate">{{ $log->message }}</td>
              <td class="px-4 py-2 text-center">
                @if($log->channel === 'whatsapp')
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">WA</span>
                @else
                  <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">SMS</span>
                @endif
              </td>
              <td class="px-4 py-2 text-center">
                @php
                  $badge = match($log->status) {
                    'sent'    => 'bg-emerald-100 text-emerald-700',
                    'failed'  => 'bg-red-100 text-red-600',
                    default   => 'bg-gray-100 text-gray-500',
                  };
                @endphp
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $badge }}" title="{{ $log->error_message }}">
                  {{ ucfirst($log->status) }}
                </span>
              </td>
              <td class="px-4 py-2 text-right text-xs text-gray-400">{{ $log->created_at->diffForHumans() }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">No messages yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($logs->hasPages())
      <div class="px-4 py-3 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
  </div>

</div>
@endsection
