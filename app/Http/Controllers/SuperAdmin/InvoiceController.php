<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $invoices = Invoice::with(['tenant', 'term.academicYear'])
            ->when($request->search, fn ($q, $s) =>
                $q->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                  ->orWhere('invoice_number', 'like', "%{$s}%")
            )
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('superadmin.invoices.index', compact('invoices'));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Invoice $invoice): View
    {
        $invoice->load(['tenant', 'subscription', 'package', 'term.academicYear']);
        return view('superadmin.invoices.show', compact('invoice'));
    }

    // ── Generate for a specific subscription ─────────────────────────────────

    public function generate(Request $request): RedirectResponse
    {
        $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
            'package_id'      => ['required', 'integer', 'exists:subscription_packages,id'],
            'term_id'         => ['required', 'integer', 'exists:academic_terms,id'],
        ]);

        $sub     = Subscription::findOrFail($request->subscription_id);
        $package = SubscriptionPackage::findOrFail($request->package_id);
        $term    = AcademicTerm::findOrFail($request->term_id);

        $invoice = $this->invoiceService->generateForSubscription($sub, $package, $term);

        return redirect()->route('superadmin.invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' generated.');
    }

    // ── Mark paid ─────────────────────────────────────────────────────────────

    public function markPaid(Invoice $invoice): RedirectResponse
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Invoice is already marked as paid.');
        }

        $invoice->markPaid();

        return back()->with('success', 'Invoice ' . $invoice->invoice_number . ' marked as paid.');
    }

    // ── Void ─────────────────────────────────────────────────────────────────

    public function void(Invoice $invoice): RedirectResponse
    {
        if ($invoice->isPaid()) {
            return back()->with('error', 'Cannot void a paid invoice.');
        }

        $invoice->markVoid();

        return back()->with('success', 'Invoice ' . $invoice->invoice_number . ' voided.');
    }
}
