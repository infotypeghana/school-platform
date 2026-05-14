@if(isset($showGraceBanner) && $showGraceBanner)
    @php
        $urgent   = $graceIsUrgent ?? false;
        $days     = $graceDaysRemaining ?? 0;
        $bg       = $urgent ? 'bg-red-600' : 'bg-amber-500';
        $message  = match(true) {
            $days === 0 => 'This portal will be locked <strong>tonight</strong>. Renew now to avoid interruption.',
            $days === 1 => 'This portal will be locked <strong>tomorrow</strong>. Renew now.',
            $days < 3   => "This portal will go offline in <strong>{$days} days</strong>. Act now.",
            default     => "This school portal will be unavailable on <strong>"
                            . optional($graceSubscription?->grace_ends_at)->format('d M Y')
                            . "</strong>. Renew to avoid interruption.",
        };
    @endphp

    <div x-data="{ open: true }" x-show="open" class="{{ $bg }} text-white py-2 px-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">

            <div class="flex items-center gap-2 text-sm">
                <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{!! $message !!}</span>
            </div>

            <div class="flex items-center gap-3 flex-shrink-0">
                @if(isset($graceSubscription))
                    <a href="{{ url('/pay/' . (app('currentTenant')->slug ?? '')) }}"
                       class="bg-white text-gray-900 text-xs font-semibold px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        Renew Now →
                    </a>
                @endif

                <button @click="open = false"
                        class="text-white/80 hover:text-white transition-colors"
                        aria-label="Dismiss">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

        </div>
    </div>
@endif
