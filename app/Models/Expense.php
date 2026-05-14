<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory, HasTenantScope;

    protected $fillable = [
        'tenant_id', 'created_by', 'description', 'category',
        'amount', 'date', 'reference', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public const CATEGORIES = [
        'salaries'    => 'Salaries & Wages',
        'utilities'   => 'Utilities',
        'supplies'    => 'Supplies & Materials',
        'maintenance' => 'Maintenance & Repairs',
        'transport'   => 'Transport',
        'other'       => 'Other',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
}
