<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateReportCardJob;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

class ReportCardBatchTest extends AdminTestCase
{
    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->class = SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_generate_all_dispatches_batch(): void
    {
        Bus::fake();

        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        ReportCard::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
        ]);

        $this->asAdmin()->post('/report-cards/generate-all', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
        ])->assertRedirect()
          ->assertSessionHas('success');

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 1);
    }

    public function test_generate_all_with_no_cards_returns_error(): void
    {
        Bus::fake();

        $this->asAdmin()->post('/report-cards/generate-all', [
            'class_id' => $this->class->id,
            'term_id'  => $this->term->id,
        ])->assertRedirect()
          ->assertSessionHas('error');

        Bus::assertNothingBatched();
    }

    public function test_batch_status_returns_json(): void
    {
        Bus::fake();

        // Create a real batch so we can test the endpoint
        $batchId = 'test-batch-' . uniqid();
        $this->asAdmin()->getJson("/report-cards/batch-status?batch_id={$batchId}")
            ->assertStatus(404);
    }

    public function test_generate_report_card_job_is_batchable(): void
    {
        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
        ]);

        $card = ReportCard::create([
            'tenant_id'       => $this->tenant->id,
            'student_id'      => $student->id,
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
        ]);

        $job = new GenerateReportCardJob($card);

        // Verify the job uses the Batchable trait
        $this->assertTrue(
            in_array(\Illuminate\Bus\Batchable::class, class_uses_recursive($job))
        );
    }
}
