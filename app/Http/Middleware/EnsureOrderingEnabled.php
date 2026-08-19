<?php // app/Http/Middleware/EnsureOrderingEnabled.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the bag and the checkout while the storefront is a preview.
 *
 * A 404 rather than a hidden link, because hiding a button is not a guarantee:
 * the URLs are guessable, and the checkout resolves the payment gateway, which
 * refuses to run outside local/testing and would 500 in the visitor's face.
 *
 * Applied as middleware rather than by skipping the route registration, so the
 * route names always exist. Registering conditionally meant any lingering
 * route('storefront.cart') anywhere in a view would throw RouteNotFoundException
 * instead of simply rendering nothing — trading a closed shop for a broken one.
 */
class EnsureOrderingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('shop.ordering'), 404);

        return $next($request);
    }
}
