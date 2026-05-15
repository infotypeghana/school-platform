<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemeOfWorkWeek extends Model
{
    protected $fillable = [
        'scheme_of_work_id', 'week_number', 'week_ending',
        'topic', 'learning_objectives', 'competencies', 'reference', 'is_completed',
    ];

    protected $casts = [
        'week_ending'  => 'date',
        'is_completed' => 'boolean',
        'week_number'  => 'integer',
    ];

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(SchemeOfWork::class, 'scheme_of_work_id');
    }
}
