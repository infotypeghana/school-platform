<?php

namespace App\Models;

use App\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsLog extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'tenant_id', 'recipient', 'message', 'channel',
        'status', 'provider_ref', 'error_message',
        'context_type', 'context_id', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Polymorphic context (Fee, Attendance, etc.)
     */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
