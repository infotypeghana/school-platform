<?php

namespace Tests\Feature\Admin;

use App\Models\Expense;
use App\Models\Fee;
use App\Models\Student;

class FinanceTest extends AdminTestCase
{
    // ── Finance dashboard ─────────────────────────────────────────────────────

    public function test_finance_index_returns_200(): void
    {
        $this->asAdmin()->get('/finance')->assertOk()->assertViewIs('admin.finance.index');
    }

    public function test_finance_index_shows_totals(): void
    {
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);
        Fee::create([
            'tenant_id'  => $this->tenant->id,
            'student_id' => $student->id,
            'term_id'    => $this->term->id,
            'fee_type'   => 'Tuition',
            'amount'     => 500,
            'amount_paid'=> 300,
        ]);

        $response = $this->asAdmin()->get('/finance');
        $response->assertOk();
        $response->assertViewHas('totalBilled');
        $response->assertViewHas('totalPaid');
    }

    public function test_guest_redirected_from_finance(): void
    {
        $this->asGuest()->get('/finance')->assertRedirect();
    }

    // ── Ledger ────────────────────────────────────────────────────────────────

    public function test_ledger_returns_200(): void
    {
        $this->asAdmin()->get('/finance/ledger')->assertOk()->assertViewIs('admin.finance.ledger');
    }

    public function test_ledger_filters_by_type(): void
    {
        $this->asAdmin()->get('/finance/ledger?type=income')->assertOk();
        $this->asAdmin()->get('/finance/ledger?type=expense')->assertOk();
    }

    // ── Expense CRUD ──────────────────────────────────────────────────────────

    public function test_create_expense_form_returns_200(): void
    {
        $this->asAdmin()->get('/finance/expenses/create')->assertOk()->assertViewIs('admin.finance.expense-form');
    }

    public function test_store_expense_creates_record(): void
    {
        $this->asAdmin()->post('/finance/expenses', [
            'description' => 'Electricity bill',
            'category'    => 'utilities',
            'amount'      => '250.00',
            'date'        => today()->format('Y-m-d'),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'description' => 'Electricity bill',
            'category'    => 'utilities',
        ]);
    }

    public function test_store_expense_validates_required_fields(): void
    {
        $this->asAdmin()->post('/finance/expenses', [])
            ->assertSessionHasErrors(['description', 'category', 'amount', 'date']);
    }

    public function test_update_expense_changes_amount(): void
    {
        $expense = Expense::create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->user->id,
            'description' => 'Old description',
            'category'    => 'supplies',
            'amount'      => 100,
            'date'        => today(),
        ]);

        $this->asAdmin()->put("/finance/expenses/{$expense->id}", [
            'description' => 'New description',
            'category'    => 'utilities',
            'amount'      => '180.00',
            'date'        => today()->format('Y-m-d'),
        ]);

        $this->assertDatabaseHas('expenses', [
            'id'     => $expense->id,
            'amount' => 180.00,
        ]);
    }

    public function test_destroy_expense_removes_record(): void
    {
        $expense = Expense::create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->user->id,
            'description' => 'Delete me',
            'category'    => 'other',
            'amount'      => 50,
            'date'        => today(),
        ]);

        $this->asAdmin()->delete("/finance/expenses/{$expense->id}")->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    // ── Net surplus / deficit ─────────────────────────────────────────────────

    public function test_net_surplus_is_income_minus_expenses(): void
    {
        // Create income
        $student = Student::factory()->create(['tenant_id' => $this->tenant->id]);
        Fee::create([
            'tenant_id'   => $this->tenant->id,
            'student_id'  => $student->id,
            'term_id'     => $this->term->id,
            'fee_type'    => 'Tuition',
            'amount'      => 1000,
            'amount_paid' => 800,
        ]);

        // Create expense
        Expense::create([
            'tenant_id'   => $this->tenant->id,
            'created_by'  => $this->user->id,
            'description' => 'Salary',
            'category'    => 'salaries',
            'amount'      => 300,
            'date'        => today(),
        ]);

        $response = $this->asAdmin()->get('/finance');
        $response->assertViewHas('netSurplus', fn ($v) => $v == 500.0); // 800 - 300
    }
}
