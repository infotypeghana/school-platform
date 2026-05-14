<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SmsService $sms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sms = new SmsService();
    }

    // ── Template constants ────────────────────────────────────────────────────

    public function test_wa_templates_constant_has_all_keys(): void
    {
        $expected = ['fee_payment', 'absent_alert', 'result_ready', 'announcement', 'admission'];

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, SmsService::WA_TEMPLATES);
        }
    }

    public function test_wa_templates_values_are_non_empty_strings(): void
    {
        foreach (SmsService::WA_TEMPLATES as $key => $name) {
            $this->assertIsString($name, "Template '{$key}' name should be a string");
            $this->assertNotEmpty($name, "Template '{$key}' name should not be empty");
        }
    }

    // ── isConfigured ─────────────────────────────────────────────────────────

    public function test_is_not_configured_when_credentials_empty(): void
    {
        // Default config in tests has no Hubtel credentials
        $this->assertFalse($this->sms->isConfigured());
    }

    // ── send — unconfigured path ──────────────────────────────────────────────

    public function test_send_returns_false_when_unconfigured(): void
    {
        Http::fake(); // ensure no real HTTP call

        $result = $this->sms->send('0241234567', 'Hello test');
        $this->assertFalse($result);
    }

    public function test_send_logs_failed_status_when_unconfigured(): void
    {
        Http::fake();

        $tenant = Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'test-sms',
            'name'   => 'Test School',
            'email'  => 'admin@smstest.edu.gh',
            'status' => 'active',
        ]);

        $this->sms->send('0241234567', 'Test message', $tenant);

        $this->assertDatabaseHas('sms_logs', [
            'tenant_id' => $tenant->id,
            'status'    => 'failed',
            'channel'   => 'sms',
        ]);
    }

    // ── sendWhatsApp — unconfigured path ──────────────────────────────────────

    public function test_send_whatsapp_returns_false_when_unconfigured(): void
    {
        Http::fake();
        $result = $this->sms->sendWhatsApp('0241234567', 'Hello WhatsApp');
        $this->assertFalse($result);
    }

    // ── sendWhatsAppTemplate — unconfigured path ──────────────────────────────

    public function test_send_whatsapp_template_returns_false_when_unconfigured(): void
    {
        Http::fake();
        $result = $this->sms->sendWhatsAppTemplate('0241234567', 'fee_payment', ['Kofi', '500.00', '0.00', 'Test School']);
        $this->assertFalse($result);
    }

    public function test_send_whatsapp_template_logs_with_whatsapp_channel(): void
    {
        Http::fake();

        $tenant = Tenant::create([
            'uuid'   => \Illuminate\Support\Str::uuid(),
            'slug'   => 'test-wa',
            'name'   => 'Test School WA',
            'email'  => 'admin@watest.edu.gh',
            'status' => 'active',
        ]);

        $this->sms->sendWhatsAppTemplate(
            '0241234567',
            'absent_alert',
            ['Kofi Mensah', '2026-05-14', 'Test School'],
            $tenant,
        );

        $this->assertDatabaseHas('sms_logs', [
            'tenant_id' => $tenant->id,
            'channel'   => 'whatsapp',
            'status'    => 'failed', // unconfigured → failed
        ]);
    }

    // ── notifyFeePayment / notifyAbsence conveniences ─────────────────────────

    public function test_notify_fee_payment_returns_false_when_unconfigured(): void
    {
        Http::fake();
        $result = $this->sms->notifyFeePayment('0241234567', 'Kofi', 500.00, 0.00, 'Test School');
        $this->assertFalse($result);
    }

    public function test_notify_absence_returns_false_when_unconfigured(): void
    {
        Http::fake();
        $result = $this->sms->notifyAbsence('0241234567', 'Kofi Mensah', '2026-05-14', 'Test School');
        $this->assertFalse($result);
    }

    // ── HTTP success path ─────────────────────────────────────────────────────

    public function test_send_returns_true_on_hubtel_success(): void
    {
        config(['services.hubtel.client_id'     => 'test-id']);
        config(['services.hubtel.client_secret' => 'test-secret']);
        config(['services.hubtel.sender_id'     => 'TestSMS']);

        $sms = new SmsService(); // re-instantiate to pick up new config

        Http::fake([
            'smsc.hubtel.com/*' => Http::response(['Status' => 0, 'Data' => ['MessageId' => 'MSG123']], 200),
        ]);

        $result = $sms->send('0241234567', 'Hello from test');
        $this->assertTrue($result);

        $this->assertDatabaseHas('sms_logs', [
            'status'       => 'sent',
            'provider_ref' => 'MSG123',
        ]);
    }

    public function test_whatsapp_template_sends_correct_payload(): void
    {
        config(['services.hubtel.client_id'     => 'test-id']);
        config(['services.hubtel.client_secret' => 'test-secret']);

        $sms = new SmsService();

        Http::fake([
            'api.hubtel.com/*' => Http::response(['data' => ['messageId' => 'WA999']], 200),
        ]);

        $result = $sms->sendWhatsAppTemplate(
            '0241234567',
            'fee_payment',
            ['Kofi Mensah', '500.00', '0.00', 'Accra Academy'],
        );

        $this->assertTrue($result);

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'template') &&
            $req->data()['template']['name'] === SmsService::WA_TEMPLATES['fee_payment']
        );
    }
}
