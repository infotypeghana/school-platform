<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'package_id', 'term_id',
        'invoice_number', 'package_name', 'billing_cycle',
        'student_count', 'price_per_student', 'amount',
        'status', 'due_date', 'paid_at', 'notes',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'paid_at'           => 'datetime',
        'student_count'     => 'integer',
        'price_per_student' => 'decimal:2',
        'amount'            => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'sent']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('due_date', '<', today());
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && ! $this->isVoid() && $this->due_date->isPast();
    }

    public function markPaid(?Carbon $at = null): void
    {
        $this->update(['status' => 'paid', 'paid_at' => $at ?? now()]);
    }

    public function markVoid(): void
    {
        $this->update(['status' => 'void']);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'paid'  => 'bg-emerald-100 text-emerald-700',
            'void'  => 'bg-gray-100 text-gray-500',
            default => $this->isOverdue()
                       ? 'bg-red-100 text-red-700'
                       : 'bg-amber-100 text-amber-700',
        };
    }

    public function statusLabel(): string
    {
        if ($this->status === 'sent' && $this->isOverdue()) {
            return 'Overdue';
        }
        return ucfirst($this->status);
    }

    // ── Invoice number generator ──────────────────────────────────────────────

    /**
     * Generate the next sequential invoice number for the current year.
     * Must be called inside a DB transaction to be race-condition safe.
     */
    public static function nextNumber(): string
    {
        $year   = now()->year;
        $prefix = "INV-{$year}-";

        $max = (int) DB::table('invoices')
            ->where('invoice_number', 'like', $prefix . '%')
            ->max(DB::raw('CAST(SUBSTR(invoice_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED)'));

        return $prefix . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
    }
}
