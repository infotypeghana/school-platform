<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumStrand extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'subject_id', 'name', 'code', 'description', 'order_index',
    ];

    protected $casts = ['order_index' => 'integer'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function subStrands(): HasMany
    {
        return $this->hasMany(CurriculumSubStrand::class, 'strand_id')->orderBy('order_index');
    }
}
