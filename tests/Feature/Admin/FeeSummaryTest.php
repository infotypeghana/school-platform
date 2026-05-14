<?php

namespace Tests\Feature\Admin;

use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;

class FeeSummaryTest extends AdminTestCase
{
    // ── Summary page access ───────────────────────────────────────────────────

    public function test_summary_page_returns_200(): void
    {
        $response = $this->asAdmin()->get('/fees/summary');

        $response->assertOk();
        $response->assertViewIs('admin.fees.summary');
    }

    public function test_summary_passes_required_view_variables(): void
    {
        $response = $this->asAdmin()->get('/fees/summary');

        $response->assertViewHas('terms');
        $response->assertViewHas('summary');
        $response->assertViewHas('grandTotal');
        $response->assertViewHas('grandPaid');
        $response->assertViewHas('grandBalance');
    }

    // ── Aggregate accuracy ────────────────────────────────────────────────────

    public function test_summary_grand_total_matches_fee_data(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 600,
            'amount_paid' => 400,
        ]);
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Feeding',
            'amount'      => 200,
            'amount_paid' => 200,
        ]);

        $response = $this->asAdmin()->get("/fees/summary?term_id={$this->term->id}");

        $response->assertViewHas('grandTotal', 800.0);
        $response->assertViewHas('grandPaid', 600.0);
        $response->assertViewHas('grandBalance', 200.0);
    }

    public function test_summary_per_class_row_has_correct_totals(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 300,
        ]);

        $response = $this->asAdmin()->get("/fees/summary?term_id={$this->term->id}");

        $summary = $response->viewData('summary');
        $this->assertCount(1, $summary);

        $row = $summary[0];
        $this->assertSame(1, $row['fee_count']);
        $this->assertSame(500.0, $row['total_levied']);
        $this->assertSame(300.0, $row['total_paid']);
        $this->assertSame(200.0, $row['total_balance']);
    }

    public function test_summary_calculates_collection_rate(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 1000,
            'amount_paid' => 750,
        ]);

        $response = $this->asAdmin()->get("/fees/summary?term_id={$this->term->id}");

        $summary = $response->viewData('summary');
        $this->assertSame(75.0, $summary[0]['collection_rate']);
    }

    public function test_summary_status_split_counts_are_correct(): void
    {
        [$class, $student1] = $this->makeClassAndStudent();
        $student2 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
        ]);
        $student3 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
        ]);

        // paid
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student1->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 500,
        ]);
        // partial
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student2->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 250,
        ]);
        // unpaid
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student3->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 0,
        ]);

        $response = $this->asAdmin()->get("/fees/summary?term_id={$this->term->id}");

        $summary = $response->viewData('summary');
        $this->assertCount(1, $summary);

        $row = $summary[0];
        $this->assertSame(1, $row['paid_count']);
        $this->assertSame(1, $row['partial_count']);
        $this->assertSame(1, $row['unpaid_count']);
    }

    public function test_summary_filters_by_term(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        // Fee for current term
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 400,
            'amount_paid' => 400,
        ]);

        // Summary filtered to a non-existent term — should produce no rows
        $response = $this->asAdmin()->get('/fees/summary?term_id=9999');

        $summary = $response->viewData('summary');
        $this->assertCount(0, $summary);
    }

    public function test_summary_skips_classes_with_no_fee_records(): void
    {
        // Class with students but no fees
        SchoolClass::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->asAdmin()->get("/fees/summary?term_id={$this->term->id}");

        $summary = $response->viewData('summary');
        $this->assertCount(0, $summary);
    }

    // ── Index tab links ───────────────────────────────────────────────────────

    public function test_fee_index_shows_summary_tab_link(): void
    {
        $response = $this->asAdmin()->get('/fees');

        $response->assertOk();
        $response->assertSee('Class Summary');
    }

    public function test_fee_summary_shows_all_records_link(): void
    {
        $response = $this->asAdmin()->get('/fees/summary');

        $response->assertSee('View All Records');
    }

    // ── Quick payment ─────────────────────────────────────────────────────────

    public function test_quick_payment_records_partial_payment(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        $fee = Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 500,
            'amount_paid' => 0,
        ]);

        $response = $this->asAdmin()->post("/fees/{$fee->id}/payment", [
            'payment_amount' => 200,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fee->refresh();
        $this->assertSame(200.0, $fee->amount_paid);
        $this->assertSame(300.0, $fee->balance);
        $this->assertSame('partial', $fee->status);
    }

    public function test_quick_payment_caps_at_full_amount(): void
    {
        [$class, $student] = $this->makeClassAndStudent();

        $fee = Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 300,
            'amount_paid' => 250,
        ]);

        // Overpayment attempt
        $this->asAdmin()->post("/fees/{$fee->id}/payment", [
            'payment_amount' => 200, // would exceed amount (250+200=450 > 300)
        ]);

        $fee->refresh();
        $this->assertSame(300.0, $fee->amount_paid); // capped at 300
        $this->assertSame(0.0,   $fee->balance);
        $this->assertSame('paid', $fee->status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Creates a class and one active student in that class for $this->tenant.
     *
     * @return array{SchoolClass, Student}
     */
    private function makeClassAndStudent(): array
    {
        $class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $student = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $class->id,
            'status'          => 'active',
        ]);

        return [$class, $student];
    }
}
