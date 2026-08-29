<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiClientRateLimit
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->attributes->get('api_client');
        if (!$client) {
            return response()->json(['status' => false, 'message' => 'API client missing.'], 401);
        }

        $limit = max(1, (int) ($client->rate_limit ?: config('services.api.default_rate_limit')));
        $key = 'api-client:'.$client->id;

        if ($this->limiter->tooManyAttempts($key, $limit)) {
            return response()->json([
                'status' => false,
                'message' => 'Rate limit exceeded.',
                'retry_after' => $this->limiter->availableIn($key),
            ], 429)->header('Retry-After', (string) $this->limiter->availableIn($key));
        }

        $this->limiter->hit($key, 60);
        $response = $next($request);
        $remaining = max(0, $limit - $this->limiter->attempts($key));
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        return $response;
    }
}
