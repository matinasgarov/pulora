<?php // app/Http/Middleware/SetLocale.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['en', 'az'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        abort_unless(in_array($locale, self::SUPPORTED, true), 404);

        app()->setLocale($locale);

        // So route('storefront.product', $slug) keeps the visitor's language
        // without every call site repeating it.
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
