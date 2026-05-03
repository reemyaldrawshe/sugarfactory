<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locale = auth()->user()['lang'] ?? $request->header('Accept-Language', 'ar');
        app()->setLocale($locale);  // Set the locale for the current request
        return $next($request);
    }
}
