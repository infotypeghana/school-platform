<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatch SMS or WhatsApp messages asynchronously.
 *
 * Usage:
 *   SendSmsJob::dispatch($phone, $message, $tenant, $context);
 *   SendSmsJob::dispatchAfterResponse($phone, $message); // fire-and-forget at end of request
 *
 * For WhatsApp template messages use the 'template' channel:
 *   SendSmsJob::dispatch($phone, $templateKey, $tenant, $context, 'template', $variables);
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        private readonly string  $to,
        private readonly string  $message,
        private readonly ?Tenant $tenant    = null,
        private readonly mixed   $context   = null,
        private readonly string  $channel   = 'sms',   // 'sms' | 'whatsapp' | 'template'
        private readonly array   $variables = [],       // for 'template' channel
    ) {}

    public function handle(SmsService $sms): void
    {
        match ($this->channel) {
            'whatsapp' => $sms->sendWhatsApp($this->to, $this->message, $this->tenant, $this->context),
            'template' => $sms->sendWhatsAppTemplate(
                $this->to,
                $this->message,       // templateKey when channel = template
                $this->variables,
                $this->tenant,
                $this->context,
            ),
            default    => $sms->send($this->to, $this->message, $this->tenant, $this->context),
        };
    }

    public function tags(): array
    {
        return ['sms', "channel:{$this->channel}", "tenant:{$this->tenant?->id}"];
    }
}
