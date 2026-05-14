<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::with('tenant')
            ->when($request->search, fn ($q, $s) =>
                $q->where(function ($q) use ($s) {
                    $q->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                      ->orWhere('reference', 'like', "%{$s}%");
                })
            )
            ->when($request->status,  fn ($q, $s) => $q->where('status',  $s))
            ->when($request->gateway, fn ($q, $g) => $q->where('gateway', $g))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        // Unfiltered total revenue (always shows grand total, not just current filter)
        $totalRevenue = Payment::where('status', Payment::STATUS_SUCCESS)->sum('amount');

        return view('superadmin.payments.index', compact('payments', 'totalRevenue'));
    }
}
