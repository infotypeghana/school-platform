<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Automatically scopes all queries to the current tenant.
 *
 * USAGE: Add `use HasTenantScope;` to any school-data model.
 *
 * SAFETY: This trait enforces tenant_id on every query, create, and relationship.
 * A model without this trait on tenant-specific data is a data leak risk.
 */
trait HasTenantScope
{
    public static function bootHasTenantScope(): void
    {
        // Auto-scope all queries to current tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = static::resolveTenantId()) {
                $builder->where((new static)->getTable() . '.tenant_id', $tenantId);
            }
        });

        // Auto-fill tenant_id on create
        static::creating(function (Model $model) {
            if (empty($model->tenant_id) && $tenantId = static::resolveTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Bypass tenant scope — use only in Super Admin context.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    private static function resolveTenantId(): ?int
    {
        // Resolve from IoC container (set by ResolveTenantMiddleware)
        if (app()->bound('currentTenant')) {
            return app('currentTenant')?->id;
        }
        return null;
    }
}
