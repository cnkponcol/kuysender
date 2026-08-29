<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiClientAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        if (!preg_match('/^kuy_([A-Za-z0-9]{12,24})\.([A-Za-z0-9_-]{32,128})$/', $token, $matches)) {
            return response()->json(['status' => false, 'message' => 'Invalid API credentials.'], 401);
        }

        $client = ApiClient::where('key_id', $matches[1])->where('is_active', true)->first();
        if (!$client || !hash_equals($client->secret_hash, hash('sha256', $matches[2]))) {
            return response()->json(['status' => false, 'message' => 'Invalid API credentials.'], 401);
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
