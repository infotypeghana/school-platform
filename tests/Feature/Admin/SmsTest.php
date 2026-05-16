<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendSmsJob;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

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

    // ── Broadcast ─────────────────────────────────────────────────────────────

    public function test_broadcast_requires_audience_message_and_channel(): void
    {
        $this->asAdmin()->post('/sms/broadcast', [])
            ->assertSessionHasErrors(['audience', 'message', 'channel']);
    }

    public function test_broadcast_all_parents_dispatches_one_job_per_guardian(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0241111111',
            'status'          => 'active',
        ]);
        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0242222222',
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/sms/broadcast', [
            'audience' => 'all_parents',
            'message'  => 'School resumes Monday. Please ensure fees are paid.',
            'channel'  => 'sms',
        ])->assertRedirect()->assertSessionHas('success');

        Queue::assertPushed(SendSmsJob::class, 2);
    }

    public function test_broadcast_deduplicates_same_phone_number(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        // Two students sharing the same guardian phone
        Student::factory()->count(2)->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0243333333',
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/sms/broadcast', [
            'audience' => 'all_parents',
            'message'  => 'Reminder: PTA meeting tomorrow at 9am.',
            'channel'  => 'sms',
        ]);

        // Only one job dispatched despite two students
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_broadcast_class_audience_only_targets_that_class(): void
    {
        Queue::fake();

        $classA = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
        $classB = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $classA->id,
            'guardian_phone'  => '0244444444',
            'status'          => 'active',
        ]);
        Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $classB->id,
            'guardian_phone'  => '0245555555',
            'status'          => 'active',
        ]);

        $this->asAdmin()->post('/sms/broadcast', [
            'audience' => "class:{$classA->id}",
            'message'  => 'Basic 4 outing trip is tomorrow.',
            'channel'  => 'sms',
        ]);

        // Only one job for classA's guardian
        Queue::assertPushed(SendSmsJob::class, 1);
    }

    public function test_broadcast_returns_error_when_no_recipients_found(): void
    {
        Queue::fake();

        // No students in the database → no phones
        $this->asAdmin()->post('/sms/broadcast', [
            'audience' => 'all_parents',
            'message'  => 'Hello parents.',
            'channel'  => 'sms',
        ])->assertRedirect()->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_broadcast_overdue_fees_targets_correct_students(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        $studentOwing = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0246666666',
            'status'          => 'active',
        ]);
        $studentClear = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'guardian_phone'  => '0247777777',
            'status'          => 'active',
        ]);

        // Only the first student has an outstanding balance
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $studentOwing->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 300,
            'amount_paid' => 0,
            'balance'     => 300,
            'status'      => 'unpaid',
            'due_date'    => now()->addDays(7)->toDateString(),
        ]);

        $this->asAdmin()->post('/sms/broadcast', [
            'audience' => 'overdue_fees',
            'message'  => 'Reminder: Please clear your ward\'s outstanding fees.',
            'channel'  => 'sms',
        ]);

        Queue::assertPushed(SendSmsJob::class, 1);
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
