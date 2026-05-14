<?php

namespace Tests\Feature\Admin;

use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;

class BulkFeeTest extends AdminTestCase
{
    private SchoolClass $class;
    private Student $student1;
    private Student $student2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Basic 6',
        ]);

        $this->student1 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);

        $this->student2 = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'active',
        ]);
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public function test_bulk_create_form_returns_200(): void
    {
        $response = $this->asAdmin()->get('/fees/bulk');

        $response->assertOk();
        $response->assertViewIs('admin.fees.bulk');
    }

    public function test_guest_redirected_from_bulk_form(): void
    {
        $this->asGuest()->get('/fees/bulk')->assertRedirect();
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_bulk_store_creates_fee_for_every_active_student(): void
    {
        $response = $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'fee_type'        => 'Tuition',
            'amount'          => 500,
        ]);

        $response->assertRedirect(route('admin.fees'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fees', [
            'student_id' => $this->student1->id,
            'term_id'    => $this->term->id,
            'fee_type'   => 'Tuition',
            'amount'     => 500,
        ]);
        $this->assertDatabaseHas('fees', [
            'student_id' => $this->student2->id,
            'term_id'    => $this->term->id,
            'fee_type'   => 'Tuition',
            'amount'     => 500,
        ]);
    }

    public function test_bulk_store_skips_existing_fees_for_same_term_and_type(): void
    {
        // Pre-create a fee for student1 for the same term and type
        Fee::create([
            'tenant_id'  => $this->tenant->id,
            'student_id' => $this->student1->id,
            'term_id'    => $this->term->id,
            'fee_type'   => 'Feeding',
            'amount'     => 200,
            'amount_paid'=> 0,
        ]);

        $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'fee_type'        => 'Feeding',
            'amount'          => 200,
        ]);

        // Only 1 new record should exist for student1 (the original)
        $this->assertSame(
            1,
            Fee::where('student_id', $this->student1->id)
               ->where('fee_type', 'Feeding')
               ->count()
        );

        // student2 gets a new record
        $this->assertDatabaseHas('fees', [
            'student_id' => $this->student2->id,
            'fee_type'   => 'Feeding',
        ]);
    }

    public function test_bulk_store_ignores_inactive_students(): void
    {
        $inactive = Student::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'school_class_id' => $this->class->id,
            'status'          => 'withdrawn',
        ]);

        $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'fee_type'        => 'Exam Fee',
            'amount'          => 50,
        ]);

        $this->assertDatabaseMissing('fees', [
            'student_id' => $inactive->id,
            'fee_type'   => 'Exam Fee',
        ]);
    }

    public function test_bulk_store_sets_amount_paid_when_provided(): void
    {
        $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'fee_type'        => 'Uniform',
            'amount'          => 100,
            'amount_paid'     => 50,
        ]);

        $this->assertDatabaseHas('fees', [
            'student_id'  => $this->student1->id,
            'fee_type'    => 'Uniform',
            'amount_paid' => 50,
            'status'      => 'partial',
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_bulk_store_requires_class(): void
    {
        $response = $this->asAdmin()->post('/fees/bulk', [
            'term_id'  => $this->term->id,
            'fee_type' => 'Tuition',
            'amount'   => 500,
        ]);

        $response->assertSessionHasErrors('school_class_id');
    }

    public function test_bulk_store_requires_term(): void
    {
        $response = $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'fee_type'        => 'Tuition',
            'amount'          => 500,
        ]);

        $response->assertSessionHasErrors('term_id');
    }

    public function test_bulk_store_requires_fee_type(): void
    {
        $response = $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'amount'          => 500,
        ]);

        $response->assertSessionHasErrors('fee_type');
    }

    public function test_bulk_store_requires_positive_amount(): void
    {
        $response = $this->asAdmin()->post('/fees/bulk', [
            'school_class_id' => $this->class->id,
            'term_id'         => $this->term->id,
            'fee_type'        => 'Tuition',
            'amount'          => 0,
        ]);

        $response->assertSessionHasErrors('amount');
    }
}
