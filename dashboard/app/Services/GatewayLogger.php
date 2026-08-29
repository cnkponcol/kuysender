<?php

namespace App\Services;

use App\Models\GatewayLog;

class GatewayLogger
{
    public static function write(string $category, string $message, array $context = [], string $level = 'info', ?int $userId = null, ?string $sessionId = null, ?string $apiClientId = null): void
    {
        try {
            GatewayLog::create([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'api_client_id' => $apiClientId,
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => $context ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Logging must not interrupt gateway operations.
        }
    }
}
