<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;

class SmsTest extends AdminTestCase
{
    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_sms_index_returns_200(): void
    {
        $this->asAdmin()->get('/sms')->assertOk()->assertViewIs('admin.sms.index');
    }

    public function test_sms_index_shows_stats(): void
    {
        $this->asAdmin()->get('/sms')->assertViewHas('stats');
    }

    public function test_guest_redirected_from_sms(): void
    {
        $this->asGuest()->get('/sms')->assertRedirect();
    }

    // ── Manual send ───────────────────────────────────────────────────────────

    public function test_send_validates_required_fields(): void
    {
        $this->asAdmin()->post('/sms/send', [])
            ->assertSessionHasErrors(['recipient', 'message', 'channel']);
    }

    public function test_send_creates_log_entry_even_when_unconfigured(): void
    {
        // No Hubtel credentials → logs as 'failed' but does not crash
        $this->asAdmin()->post('/sms/send', [
            'recipient' => '0241234567',
            'message'   => 'Test message',
            'channel'   => 'sms',
        ])->assertRedirect();

        $this->assertDatabaseHas('sms_logs', [
            'recipient' => '+233241234567',
            'channel'   => 'sms',
        ]);
    }

    // ── Automatic triggers ────────────────────────────────────────────────────

    public function test_fee_payment_triggers_sms_to_guardian(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0201234567',
            'status'          => 'active',
        ]);

        $fee = Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 0,
        ]);

        $this->asAdmin()->post("/fees/{$fee->id}/payment", [
            'payment_amount' => '200',
        ])->assertRedirect();

        $this->assertDatabaseHas('sms_logs', [
            'tenant_id'    => $this->tenant->id,
            'recipient'    => '+233201234567',
            'context_type' => Fee::class,
            'context_id'   => $fee->id,
        ]);
    }

    public function test_absent_attendance_triggers_sms_to_guardian(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0551234567',
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/attendance/save', [
            'class_id'   => $class->id,
            'date'       => today()->format('Y-m-d'),
            'term_id'    => $this->term->id,
            'attendance' => [$student->id => 'absent'],
        ])->assertRedirect();

        $this->assertDatabaseHas('sms_logs', [
            'tenant_id' => $this->tenant->id,
            'recipient' => '+233551234567',
            'channel'   => 'sms',
        ]);
    }

    public function test_present_attendance_does_not_trigger_sms(): void
    {
        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0241111111',
            'status'          => 'active',
        ]);

        $before = SmsLog::count();

        $this->asAdmin()->post('/attendance/save', [
            'class_id'   => $class->id,
            'date'       => today()->format('Y-m-d'),
            'term_id'    => $this->term->id,
            'attendance' => [$student->id => 'present'],
        ]);

        $this->assertSame($before, SmsLog::count());
    }

    // ── Phone normalisation ───────────────────────────────────────────────────

    public function test_sms_service_normalizes_local_number(): void
    {
        $svc = new SmsService();
        // Use reflection to access private method via logging a send
        // The log should contain the normalized E.164 number
        $svc->send('0241234567', 'Test', $this->tenant);

        $this->assertDatabaseHas('sms_logs', ['recipient' => '+233241234567']);
    }
}
