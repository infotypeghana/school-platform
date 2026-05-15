<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumSubStrand extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'strand_id', 'name', 'code', 'description', 'order_index',
    ];

    protected $casts = ['order_index' => 'integer'];

    public function strand(): BelongsTo
    {
        return $this->belongsTo(CurriculumStrand::class, 'strand_id');
    }
}
