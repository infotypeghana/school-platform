<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $host  = $request->getHost();
        $parts = explode('.', $host);

        // Expect: {slug}.platform.com OR admin.platform.com/{slug}
        $slug = $parts[0] ?? null;

        if (! $slug) {
            abort(404, 'School not found.');
        }

        // Try custom-domain lookup first so schools with vanity domains
        // (including 'www.*' prefixes) resolve correctly before the slug
        // reserved-word check eliminates 'www', 'admin', 'superadmin'.
        $tenant = Tenant::where('domain', $host)
            ->orWhere('slug', $slug)
            ->first();

        // Guard reserved subdomains that are not school slugs
        if (! $tenant && in_array($slug, ['www', 'superadmin', 'admin'])) {
            abort(404, 'School not found.');
        }

        if (! $tenant) {
            abort(404, 'School not found.');
        }

        $request->attributes->set('tenant', $tenant);
        app()->instance('currentTenant', $tenant);

        // Remove the {slug} domain parameter from the route so it is not
        // injected as a positional argument into controller methods.
        // The tenant is already available via app('currentTenant') and
        // $request->attributes->get('tenant').
        if ($request->route()) {
            $request->route()->forgetParameter('slug');
        }

        return $next($request);
    }
}
