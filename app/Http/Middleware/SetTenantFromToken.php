<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For API routes authenticated via Sanctum token, resolve the current
 * tenant from the authenticated user's tenant_id and bind it into the
 * IoC container so HasTenantScope works identically to web routes.
 *
 * Super admins (tenant_id = null) can pass ?school_id= to scope queries,
 * otherwise all queries are unscoped.
 */
class SetTenantFromToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);

            if (! $tenant) {
                return response()->json(['message' => 'School not found.'], 404);
            }

            app()->instance('currentTenant', $tenant);
        }

        return $next($request);
    }
}
