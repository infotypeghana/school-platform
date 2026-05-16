<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * Immutable HTTP request telemetry record.
 *
 * Written by LogRequestMiddleware::terminate() — after the response is
 * dispatched to the browser, so it adds zero latency to the request.
 *
 * Immutability: no updated_at column; save() and delete() on existing
 * rows throw LogicException (same pattern as PaymentLedger).
 *
 * @property int         $id
 * @property string      $request_id   UUID — cross-reference with audit_logs
 * @property int|null    $tenant_id
 * @property int|null    $user_id
 * @property string|null $user_type    school_admin|super_admin|teacher_portal|parent_portal|api|unauthenticated
 * @property string      $method
 * @property string      $path
 * @property string|null $route_name
 * @property int         $status_code
 * @property float       $duration_ms
 * @property string      $ip_address
 * @property string|null $user_agent
 * @property \Carbon\Carbon $created_at
 */
class RequestLog extends Model
{
    /**
     * No updated_at — this model is append-only.
     */
    const UPDATED_AT = null;

    protected $table = 'request_logs';

    protected $fillable = [
        'request_id',
        'tenant_id',
        'user_id',
        'user_type',
        'method',
        'path',
        'route_name',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'status_code' => 'integer',
        'created_at'  => 'datetime',
    ];

    // ── User type constants ────────────────────────────────────────────────────

    const TYPE_SCHOOL_ADMIN    = 'school_admin';
    const TYPE_SUPER_ADMIN     = 'super_admin';
    const TYPE_TEACHER_PORTAL  = 'teacher_portal';
    const TYPE_PARENT_PORTAL   = 'parent_portal';
    const TYPE_API             = 'api';
    const TYPE_UNAUTHENTICATED = 'unauthenticated';

    // ── Immutability guards ────────────────────────────────────────────────────

    /**
     * Prevent updates to existing rows. New records use parent::save().
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException(
                'RequestLog records are immutable — they cannot be updated after creation.'
            );
        }

        return parent::save($options);
    }

    /**
     * Prevent deletion — request logs must be pruned via the dedicated
     * PruneRequestLogsCommand which uses DB::table()->delete() directly,
     * bypassing Eloquent so this guard does not fire on bulk pruning.
     */
    public function delete(): bool|null
    {
        throw new \LogicException(
            'RequestLog records cannot be deleted via Eloquent. Use PruneRequestLogsCommand for retention-based pruning.'
        );
    }

    // ── Factory method ─────────────────────────────────────────────────────────

    /**
     * The ONLY way to write a request log entry.
     * Bypasses the save() guard by calling parent::save() directly on a new instance.
     */
    public static function record(array $attributes): static
    {
        $instance = new static($attributes);
        // parent::save() to bypass our immutability guard (row is new, not existing)
        (function () { parent::save(); })->call($instance);

        return $instance;
    }

    // ── Query scopes ───────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeSlow(Builder $query, float $thresholdMs = 1000): Builder
    {
        return $query->where('duration_ms', '>=', $thresholdMs);
    }

    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('status_code', '>=', 500);
    }

    public function scopeClientErrors(Builder $query): Builder
    {
        return $query->whereBetween('status_code', [400, 499]);
    }

    public function scopeApiRequests(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_API);
    }

    public function scopeInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isSuccessful(): bool
    {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    public function isServerError(): bool
    {
        return $this->status_code >= 500;
    }

    public function isSlow(float $thresholdMs = 1000): bool
    {
        return $this->duration_ms >= $thresholdMs;
    }
}
