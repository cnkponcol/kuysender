<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);
        $client = $request->attributes->get('api_client');

        try {
            ApiRequestLog::create([
                'api_client_id' => $client?->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // API logging must never break the request path.
        }

        return $response;
    }
}
