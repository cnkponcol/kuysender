<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $client = $request->attributes->get('api_client');
        if (!$client || !$client->hasScope($scope)) {
            return response()->json(['status' => false, 'message' => 'API scope not permitted.'], 403);
        }
        return $next($request);
    }
}
