<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $clientId;
    private string $clientSecret;
    private string $senderId;
    private string $baseUrl = 'https://smsc.hubtel.com/v1/messages/send';
    private string $waUrl   = 'https://api.hubtel.com/v1/whatsapp/messages';

    // ── Pre-approved WhatsApp template names (configure in Hubtel dashboard) ──
    // Template variables are positional: {{1}}, {{2}}, ...
    public const WA_TEMPLATES = [
        'fee_payment'    => 'school_fee_payment_receipt',   // {{1}}=name {{2}}=amount {{3}}=balance {{4}}=school
        'absent_alert'   => 'school_absence_notification',  // {{1}}=name {{2}}=date {{3}}=school
        'result_ready'   => 'school_report_card_ready',     // {{1}}=name {{2}}=term {{3}}=school
        'announcement'   => 'school_general_announcement',  // {{1}}=title {{2}}=body {{3}}=school
        'admission'      => 'school_admission_update',      // {{1}}=name {{2}}=status {{3}}=school
    ];

    public function __construct()
    {
        $this->clientId     = (string) config('services.hubtel.client_id', '');
        $this->clientSecret = (string) config('services.hubtel.client_secret', '');
        $this->senderId     = (string) config('services.hubtel.sender_id', 'SchoolMS');
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Send an SMS message.
     *
     * @param  string       $to        Phone number (local or international format)
     * @param  string       $message   Message body
     * @param  Tenant|null  $tenant    Used for logging
     * @param  mixed        $context   Eloquent model for polymorphic log (optional)
     */
    public function send(string $to, string $message, ?Tenant $tenant = null, mixed $context = null): bool
    {
        $to = $this->normalizePhone($to);

        if (! $to) {
            return false;
        }

        $log = $this->createLog($tenant, $to, $message, 'sms', $context);

        if (! $this->isConfigured()) {
            Log::warning('SmsService: Hubtel credentials not configured. Message not sent.', ['to' => $to]);
            $log->update(['status' => 'failed', 'error_message' => 'Hubtel credentials not configured']);
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(10)
                ->post($this->baseUrl, [
                    'From'    => $this->senderId,
                    'To'      => $to,
                    'Content' => $message,
                ]);

            if ($response->successful() && ($response->json('Status') === 0 || $response->json('status') === 0)) {
                $log->update([
                    'status'       => 'sent',
                    'provider_ref' => $response->json('Data.MessageId') ?? $response->json('data.messageId'),
                    'sent_at'      => now(),
                ]);
                return true;
            }

            $error = $response->json('Message') ?? $response->json('message') ?? 'Unknown error';
            $log->update(['status' => 'failed', 'error_message' => $error]);
            Log::warning('SmsService: Hubtel returned error', ['error' => $error, 'to' => $to]);
            return false;

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error('SmsService: Exception sending SMS', ['error' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }

    /**
     * Send a free-form WhatsApp text message via Hubtel.
     * NOTE: WhatsApp Business API only allows free-form messages within 24h of customer contact.
     * For outbound notifications, use sendWhatsAppTemplate() instead.
     */
    public function sendWhatsApp(string $to, string $message, ?Tenant $tenant = null, mixed $context = null): bool
    {
        $to = $this->normalizePhone($to);

        if (! $to) {
            return false;
        }

        $log = $this->createLog($tenant, $to, $message, 'whatsapp', $context);

        if (! $this->isConfigured()) {
            $log->update(['status' => 'failed', 'error_message' => 'Hubtel credentials not configured']);
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(10)
                ->post($this->waUrl, [
                    'from'    => $this->senderId,
                    'to'      => $to,
                    'content' => ['text' => $message],
                    'type'    => 'text',
                ]);

            if ($response->successful()) {
                $log->update([
                    'status'       => 'sent',
                    'provider_ref' => $response->json('data.messageId'),
                    'sent_at'      => now(),
                ]);
                return true;
            }

            $error = $response->json('message') ?? 'WhatsApp delivery failed';
            $log->update(['status' => 'failed', 'error_message' => $error]);
            return false;

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a pre-approved WhatsApp template message.
     *
     * Hubtel requires template-based messages for outbound (business-initiated)
     * notifications. Templates must be approved in the Hubtel dashboard first.
     *
     * @param  string        $to           Recipient phone number
     * @param  string        $templateKey  Key from self::WA_TEMPLATES constant
     * @param  array<string> $variables    Ordered list of variable values ({{1}}, {{2}}, ...)
     * @param  Tenant|null   $tenant       For logging
     * @param  mixed         $context      Polymorphic log context
     */
    public function sendWhatsAppTemplate(
        string $to,
        string $templateKey,
        array $variables = [],
        ?Tenant $tenant = null,
        mixed $context = null,
    ): bool {
        $to           = $this->normalizePhone($to);
        $templateName = self::WA_TEMPLATES[$templateKey] ?? $templateKey;

        if (! $to) {
            return false;
        }

        // Build a human-readable preview for the log
        $preview = "[WA Template: {$templateName}] " . implode(' | ', $variables);
        $log     = $this->createLog($tenant, $to, $preview, 'whatsapp', $context);

        if (! $this->isConfigured()) {
            $log->update(['status' => 'failed', 'error_message' => 'Hubtel credentials not configured']);
            return false;
        }

        // Build Hubtel template payload
        $components = [];
        if (! empty($variables)) {
            $params = array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], $variables);
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->timeout(10)
                ->post($this->waUrl . '/template', [
                    'from'         => $this->senderId,
                    'to'           => $to,
                    'type'         => 'template',
                    'template'     => [
                        'name'       => $templateName,
                        'language'   => ['code' => 'en'],
                        'components' => $components,
                    ],
                ]);

            if ($response->successful()) {
                $log->update([
                    'status'       => 'sent',
                    'provider_ref' => $response->json('data.messageId'),
                    'sent_at'      => now(),
                ]);
                return true;
            }

            $error = $response->json('message') ?? 'WhatsApp template delivery failed';
            $log->update(['status' => 'failed', 'error_message' => $error]);
            Log::warning('SmsService: WhatsApp template failed', [
                'template' => $templateName,
                'to'       => $to,
                'error'    => $error,
            ]);
            return false;

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Convenience: send fee payment receipt via WhatsApp template.
     * Falls back to SMS if WhatsApp fails.
     */
    public function notifyFeePayment(
        string $phone,
        string $studentName,
        float $amount,
        float $balance,
        string $schoolName,
        ?Tenant $tenant = null,
        mixed $context = null,
    ): bool {
        $waOk = $this->sendWhatsAppTemplate(
            $phone,
            'fee_payment',
            [$studentName, number_format($amount, 2), number_format($balance, 2), $schoolName],
            $tenant,
            $context,
        );

        if (! $waOk) {
            // SMS fallback
            $message = "Payment received for {$studentName}. Amount: GHS " . number_format($amount, 2)
                . ". Balance: GHS " . number_format($balance, 2) . ". — {$schoolName}";
            return $this->send($phone, $message, $tenant, $context);
        }

        return true;
    }

    /**
     * Convenience: send absence alert via WhatsApp template.
     * Falls back to SMS if WhatsApp fails.
     */
    public function notifyAbsence(
        string $phone,
        string $studentName,
        string $date,
        string $schoolName,
        ?Tenant $tenant = null,
        mixed $context = null,
    ): bool {
        $waOk = $this->sendWhatsAppTemplate(
            $phone,
            'absent_alert',
            [$studentName, $date, $schoolName],
            $tenant,
            $context,
        );

        if (! $waOk) {
            $message = "Dear Parent, {$studentName} was marked absent on {$date}. Please contact us. — {$schoolName}";
            return $this->send($phone, $message, $tenant, $context);
        }

        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /**
     * Normalize Ghanaian phone numbers to international format (+233...).
     * Handles: 0241234567, +233241234567, 233241234567
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '233')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return '+233' . substr($phone, 1);
        }

        if (strlen($phone) === 9) {
            return '+233' . $phone;
        }

        // Already international or unknown — return as-is with +
        return str_starts_with($phone, '+') ? $phone : '+' . $phone;
    }

    private function createLog(?Tenant $tenant, string $to, string $message, string $channel, mixed $context): SmsLog
    {
        return SmsLog::create([
            'tenant_id'    => $tenant?->id,
            'recipient'    => $to,
            'message'      => $message,
            'channel'      => $channel,
            'status'       => 'pending',
            'context_type' => $context ? get_class($context) : null,
            'context_id'   => $context?->id,
        ]);
    }
}
