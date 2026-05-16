<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() || $user?->isKielUser()) {
            return $next($request);
        }

        $client = $request->route('client');

        if (is_numeric($client)) {
            $client = Client::find($client);
        }

        if ($client instanceof Client && $user?->canAccessClient($client)) {
            return $next($request);
        }

        abort(403, 'You may only access your organization data.');
    }
}
