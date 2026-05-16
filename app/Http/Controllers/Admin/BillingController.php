<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(): View
    {
        $tenant   = app('currentTenant');
        $invoices = Invoice::forTenant($tenant->id)
            ->with('term.academicYear')
            ->orderByDesc('created_at')
            ->get();

        $outstanding = $invoices->where('status', '!=', 'paid')->where('status', '!=', 'void')->sum('amount');

        return view('admin.billing.index', compact('invoices', 'outstanding'));
    }
}
