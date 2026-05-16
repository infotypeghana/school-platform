<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Generate a single invoice for a subscription + package + term.
     * Safe to call inside or outside an existing transaction.
     */
    public function generateForSubscription(
        Subscription $sub,
        SubscriptionPackage $package,
        AcademicTerm $term,
        int $dueDays = 14
    ): Invoice {
        $studentCount = Student::withoutGlobalScopes()
            ->where('tenant_id', $sub->tenant_id)
            ->where('status', 'active')
            ->count();

        $billable = max($studentCount, $package->min_students);
        $amount   = round($billable * (float) $package->price_per_student, 2);

        return DB::transaction(function () use ($sub, $package, $term, $studentCount, $amount, $dueDays) {
            $invoice = Invoice::create([
                'tenant_id'         => $sub->tenant_id,
                'subscription_id'   => $sub->id,
                'package_id'        => $package->id,
                'term_id'           => $term->id,
                'invoice_number'    => Invoice::nextNumber(),
                'package_name'      => $package->name,
                'billing_cycle'     => $package->billing_cycle,
                'student_count'     => $studentCount,
                'price_per_student' => $package->price_per_student,
                'amount'            => $amount,
                'status'            => 'sent',
                'due_date'          => now()->addDays($dueDays)->toDateString(),
            ]);

            // SMS notification to school admin
            $tenant = $sub->tenant;
            $phone  = $tenant?->contact_phone ?? $tenant?->phone;
            if ($phone && $tenant) {
                $msg = 'SchoolMS: Invoice ' . $invoice->invoice_number
                    . ' of GHS ' . number_format($invoice->amount, 2)
                    . ' generated for ' . $term->term_name
                    . '. Due ' . $invoice->due_date->format('d M Y') . '.';
                SendSmsJob::dispatch($phone, $msg, $tenant, $invoice)->onQueue('default');
            }

            return $invoice;
        });
    }

    /**
     * Batch-generate invoices for every active/trial subscription that:
     *  - has a package attached
     *  - does NOT already have an invoice for this term
     *
     * Returns the number of invoices created.
     */
    public function generateTermInvoices(AcademicTerm $term): int
    {
        $count = 0;

        Subscription::whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->whereNotNull('package_id')
            ->whereDoesntHave('invoices', fn ($q) => $q->where('term_id', $term->id))
            ->with('package')
            ->each(function (Subscription $sub) use ($term, &$count) {
                try {
                    $this->generateForSubscription($sub, $sub->package, $term);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('Invoice generation failed', [
                        'subscription_id' => $sub->id,
                        'tenant_id'       => $sub->tenant_id,
                        'error'           => $e->getMessage(),
                    ]);
                }
            });

        return $count;
    }
}
