<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic Eloquent observer that writes to audit_logs for any model.
 *
 * Registration: in AppServiceProvider::boot(), call:
 *   Student::observe(AuditObserver::class);
 *   Teacher::observe(AuditObserver::class);
 *   Fee::observe(AuditObserver::class);
 *   Assessment::observe(AuditObserver::class);
 *
 * Only diffs are stored for updates — unchanged fields are omitted to keep logs clean.
 * Sensitive columns are scrubbed before storage.
 */
class AuditObserver
{
    /**
     * Columns to always exclude from audit diffs (passwords, tokens, etc.).
     */
    private const SCRUBBED = [
        'password', 'remember_token', 'api_token',
        'two_factor_recovery_codes', 'two_factor_secret',
    ];

    public function created(Model $model): void
    {
        $this->log('created', $model, null, $this->scrub($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $dirty = $model->getDirty();
        if (empty($dirty)) {
            return;
        }

        $old = [];
        $new = [];

        foreach ($this->scrub($dirty) as $key => $newVal) {
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $newVal;
        }

        $this->log('updated', $model, $old, $new);
    }

    public function deleted(Model $model): void
    {
        $this->log('deleted', $model, $this->scrub($model->getAttributes()), null);
    }

    // ── Internal ──────────────────────────────────────────────────────────────

    private function log(string $action, Model $model, ?array $old, ?array $new): void
    {
        // Skip logging during tests unless explicitly enabled
        if (app()->runningUnitTests() && ! config('audit.enabled_in_tests', false)) {
            return;
        }

        // Try to build a human-readable label
        $label = $this->labelFor($model);

        AuditLog::create([
            'tenant_id'       => app()->bound('currentTenant') ? app('currentTenant')?->id : $model->getAttribute('tenant_id'),
            'user_id'         => auth()->id(),
            'user_name'       => auth()->user()?->name,
            'action'          => $action,
            'auditable_type'  => get_class($model),
            'auditable_id'    => $model->getKey(),
            'auditable_label' => $label,
            'old_values'      => $old,
            'new_values'      => $new,
            'ip_address'      => request()->ip(),
            'user_agent'      => substr(request()->userAgent() ?? '', 0, 200),
        ]);
    }

    private function scrub(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::SCRUBBED));
    }

    private function labelFor(Model $model): string
    {
        // Common label columns — use whichever exists
        foreach (['full_name', 'name', 'title', 'email', 'receipt_number'] as $col) {
            if (isset($model->$col) && $model->$col) {
                return (string) $model->$col;
            }
        }

        return class_basename($model) . ' #' . (string) $model->getKey();
    }
}
