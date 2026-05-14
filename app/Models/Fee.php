<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'student_id', 'term_id',
        'fee_type',      // tuition, feeding, pta, uniform, etc.
        'amount',
        'amount_paid',
        'balance',
        'due_date',
        'status',        // paid, partial, unpaid
        'receipt_number',
    ];

    protected $casts = [
        'due_date'    => 'date',
        'amount'      => 'float',
        'amount_paid' => 'float',
        'balance'     => 'float',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    protected static function booted(): void
    {
        static::saving(function (Fee $fee) {
            $fee->balance = max(0, $fee->amount - $fee->amount_paid);
            $fee->status  = match (true) {
                $fee->balance <= 0              => 'paid',
                $fee->amount_paid > 0           => 'partial',
                default                         => 'unpaid',
            };
        });
    }
}
