<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalWaAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.wa.internal_token');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-WA-Internal-Token', ''));

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['status' => false, 'message' => 'Unauthorized internal request.'], 401);
        }

        return $next($request);
    }
}
