<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Expense;
use App\Models\Fee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    // ── Dashboard / P&L ──────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $terms   = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $current = AcademicTerm::current();
        $termId  = $request->input('term_id', $current?->id);
        $term    = $termId ? AcademicTerm::with('academicYear')->find($termId) : null;

        // ── Income ────────────────────────────────────────────────────────────
        $feeQuery   = Fee::when($termId, fn ($q) => $q->where('term_id', $termId));
        $totalBilled = (clone $feeQuery)->sum('amount');
        $totalPaid   = (clone $feeQuery)->sum('amount_paid');
        $totalOwed   = (clone $feeQuery)->sum('balance');

        // Income by fee type
        $incomeByType = (clone $feeQuery)
            ->select('fee_type', DB::raw('SUM(amount_paid) as collected'), DB::raw('SUM(amount) as billed'))
            ->groupBy('fee_type')
            ->orderByDesc('collected')
            ->get();

        // Monthly income trend (last 6 months)
        // Use driver-aware date truncation: MySQL needs DATE_FORMAT, SQLite needs strftime.
        $driver     = DB::connection()->getDriverName();
        $monthExpr  = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyIncome = Fee::select(
                DB::raw("{$monthExpr} as month"),
                DB::raw('SUM(amount_paid) as collected')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->limit(6)
            ->get();

        // ── Expenses ──────────────────────────────────────────────────────────
        $expenseQuery   = Expense::when($termId && $term, function ($q) use ($term) {
            $start = $term->start_date ?? null;
            $end   = $term->end_date   ?? null;
            if ($start && $end) {
                $q->whereBetween('date', [$start, $end]);
            }
        });
        $totalExpenses  = (clone $expenseQuery)->sum('amount');

        $expenseByCategory = (clone $expenseQuery)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $netSurplus = $totalPaid - $totalExpenses;

        // Recent expenses
        $recentExpenses = Expense::with('author')
            ->orderByDesc('date')
            ->limit(10)
            ->get();

        return view('admin.finance.index', compact(
            'terms', 'term', 'termId',
            'totalBilled', 'totalPaid', 'totalOwed',
            'incomeByType', 'monthlyIncome',
            'totalExpenses', 'expenseByCategory',
            'netSurplus', 'recentExpenses',
        ));
    }

    // ── Ledger ────────────────────────────────────────────────────────────────

    public function ledger(Request $request): View
    {
        $terms  = AcademicTerm::with('academicYear')->orderByDesc('id')->get();
        $termId = $request->input('term_id');
        $term   = $termId ? AcademicTerm::find($termId) : null;
        $type   = $request->input('type'); // income | expense

        // Fee payments (income entries)
        $payments = Fee::with(['student.schoolClass', 'term.academicYear'])
            ->when($termId, fn ($q) => $q->where('term_id', $termId))
            ->where('amount_paid', '>', 0)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($f) => [
                'date'        => $f->updated_at->format('Y-m-d'),
                'type'        => 'income',
                'description' => $f->fee_type . ' — ' . ($f->student?->full_name ?? ''),
                'reference'   => $f->receipt_number,
                'debit'       => null,
                'credit'      => $f->amount_paid,
            ]);

        // Expenses
        $expenses = Expense::with('author')
            ->when($term, function ($q) use ($term) {
                $start = $term->start_date ?? null;
                $end   = $term->end_date   ?? null;
                if ($start && $end) {
                    $q->whereBetween('date', [$start, $end]);
                }
            })
            ->orderByDesc('date')
            ->get()
            ->map(fn ($e) => [
                'date'        => $e->date->format('Y-m-d'),
                'type'        => 'expense',
                'description' => $e->description . ' (' . $e->category_label . ')',
                'reference'   => $e->reference,
                'debit'       => $e->amount,
                'credit'      => null,
            ]);

        // Merge & sort
        $entries = $payments->concat($expenses)->sortByDesc('date')->values();

        if ($type === 'income') {
            $entries = $entries->where('type', 'income')->values();
        } elseif ($type === 'expense') {
            $entries = $entries->where('type', 'expense')->values();
        }

        $totals = [
            'credit' => $entries->sum('credit'),
            'debit'  => $entries->sum('debit'),
        ];
        $totals['net'] = ($totals['credit'] ?? 0) - ($totals['debit'] ?? 0);

        return view('admin.finance.ledger', compact('entries', 'terms', 'termId', 'type', 'totals'));
    }

    // ── Statement PDF ─────────────────────────────────────────────────────────

    public function statement(Request $request)
    {
        $termId = $request->input('term_id');
        $term   = $termId ? AcademicTerm::with('academicYear')->find($termId) : null;
        $tenant = app('currentTenant');

        $totalBilled   = Fee::when($termId, fn ($q) => $q->where('term_id', $termId))->sum('amount');
        $totalPaid     = Fee::when($termId, fn ($q) => $q->where('term_id', $termId))->sum('amount_paid');
        $totalOwed     = Fee::when($termId, fn ($q) => $q->where('term_id', $termId))->sum('balance');

        $expenseQuery  = Expense::when($term, function ($q) use ($term) {
            $start = $term->start_date ?? null;
            $end   = $term->end_date   ?? null;
            if ($start && $end) {
                $q->whereBetween('date', [$start, $end]);
            }
        });
        $totalExpenses = (clone $expenseQuery)->sum('amount');

        $incomeByType = Fee::when($termId, fn ($q) => $q->where('term_id', $termId))
            ->select('fee_type', DB::raw('SUM(amount_paid) as collected'), DB::raw('SUM(amount) as billed'))
            ->groupBy('fee_type')
            ->get();

        $expenseByCategory = (clone $expenseQuery)
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        $netSurplus = $totalPaid - $totalExpenses;

        $pdf = Pdf::loadView('admin.finance.statement', compact(
            'tenant', 'term',
            'totalBilled', 'totalPaid', 'totalOwed',
            'totalExpenses', 'incomeByType', 'expenseByCategory',
            'netSurplus',
        ))->setPaper('a4', 'portrait');

        $filename = $term
            ? 'financial-statement-' . str($term->term_name)->slug() . '.pdf'
            : 'financial-statement.pdf';

        return $pdf->download($filename);
    }

    // ── Expenses CRUD ─────────────────────────────────────────────────────────

    public function createExpense(): View
    {
        return view('admin.finance.expense-form', ['expense' => null]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:200',
            'category'    => 'required|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'reference'   => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        $data['created_by'] = auth()->id();
        Expense::create($data);

        return redirect()->route('admin.finance.index')
            ->with('success', 'Expense recorded.');
    }

    public function editExpense(Expense $expense): View
    {
        return view('admin.finance.expense-form', compact('expense'));
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $data = $request->validate([
            'description' => 'required|string|max:200',
            'category'    => 'required|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'amount'      => 'required|numeric|min:0.01',
            'date'        => 'required|date',
            'reference'   => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        $expense->update($data);

        return redirect()->route('admin.finance.index')
            ->with('success', 'Expense updated.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();
        return redirect()->route('admin.finance.index')
            ->with('success', 'Expense deleted.');
    }
}
