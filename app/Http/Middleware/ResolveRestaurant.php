<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolve the current restaurant for storefront / public routes
 * and switch to its tenant database when available.
 *
 * Hardened:
 * - Clearer host / slug resolution order
 * - Always ends previous tenant context before switching
 * - Sets container bindings consistently
 *
 * @author Mueez Ul Rehman
 */
class ResolveRestaurant
{
    public function handle(Request $request, Closure $next)
    {
        // Skip admin / manager / auth routes — they resolve context themselves
        if (
            $request->is('admin/*') || $request->is('admin')
            || $request->is('manager/*') || $request->is('manager')
            || $request->is('ceo/*') || $request->is('ceo')
            || $request->is('password/change-required')
            || $request->is('login') || $request->is('logout')
            || $request->is('register')
        ) {
            return $next($request);
        }

        // Platform-level public pages that do not require a restaurant
        if (
            $request->path() === '' || $request->is('/')
            || $request->is('checkout') || $request->is('checkout/*')
            || $request->is('track') || $request->is('track/*')
            || $request->is('queue-token/*')
            || $request->is('queue-display/*')
            || $request->is('feedback') || $request->is('feedback/*')
            || $request->is('account') || $request->is('account/*')
            || $request->is('privacy-policy')
            || $request->is('terms')
            || $request->is('faq')
        ) {
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $restaurant = $this->resolveByHost($host, $request);

        if (! $restaurant) {
            $hostIsLocal = str_contains($host, 'localhost')
                || $host === '127.0.0.1'
                || str_ends_with($host, '.test')
                || str_contains($host, '.local');

            if ($hostIsLocal && $request->path() === '') {
                return $next($request);
            }

            abort(404, 'Restaurant not found');
        }

        // Clean any previous tenant context
        Tenancy::end();

        // Bind for the rest of the request
        $request->attributes->set('restaurant', $restaurant);
        Tenancy::setCurrent($restaurant);
        view()->share('currentRestaurant', $restaurant);

        if ($restaurant->hasTenantDatabase()) {
            Tenancy::configureTenantConnection($restaurant);
        }

        return $next($request);
    }

    protected function resolveByHost(string $host, Request $request): ?Restaurant
    {
        // 1. Exact custom domain / domain column / domains table
        $restaurant = Restaurant::where('custom_domain', $host)
            ->orWhere('domain', $host)
            ->orWhereHas('domains', fn($q) => $q->where('domain', $host))
            ->first();

        if ($restaurant) {
            return $restaurant;
        }

        // 2. Local development subdomains (slug.localhost / slug.test)
        $hostIsLocal = str_contains($host, 'localhost')
            || $host === '127.0.0.1'
            || str_ends_with($host, '.test')
            || str_contains($host, '.local');

        if ($hostIsLocal) {
            $subdomain = explode('.', $host)[0] ?? null;
            if ($subdomain && ! in_array($subdomain, ['localhost', '127', 'www'], true)) {
                $restaurant = Restaurant::where('slug', $subdomain)->first();
                if ($restaurant) {
                    return $restaurant;
                }
            }
        }

        // 3. Path-based: main platform domain serves /{slug}
        $slug = $request->segment(1);
        if ($slug && ! in_array($slug, ['admin', 'manager', 'ceo', 'track', 'checkout', 'login', 'api'], true)) {
            return Restaurant::where('slug', $slug)
                ->where('status', 'active')
                ->first();
        }

        return null;
    }
}
