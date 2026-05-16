<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKielUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isKielUser()) {
            return $next($request);
        }

        abort(403, 'Kiel workspace access is required.');
    }
}
