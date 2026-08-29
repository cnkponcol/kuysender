<?php

return [
    'wa' => [
        'url' => env('WA_SERVICE_URL', 'http://127.0.0.1:5570'),
        'internal_token' => env('WA_INTERNAL_TOKEN'),
        'timeout' => (int) env('WA_REQUEST_TIMEOUT', 20),
        'qr_ttl' => (int) env('WA_QR_TTL_SECONDS', 90),
    ],
    'api' => [
        'default_rate_limit' => (int) env('API_DEFAULT_RATE_LIMIT', 60),
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('API_ALLOWED_ORIGINS', ''))))),
    ],
    'webhook' => [
        'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),
        'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
    ],
    'ai' => [
        'provider_url' => env('AI_DEFAULT_PROVIDER_URL'),
        'api_key' => env('AI_DEFAULT_API_KEY'),
        'model' => env('AI_DEFAULT_MODEL'),
        'max_context_messages' => (int) env('AI_MAX_CONTEXT_MESSAGES', 12),
        'trusted_internal_targets' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_TRUSTED_INTERNAL_TARGETS', ''))))),
    ],
    'broadcast' => [
        'worker_batch' => (int) env('BROADCAST_WORKER_BATCH', 50),
        'min_delay' => (int) env('BROADCAST_MIN_DELAY_SECONDS', 15),
        'max_delay' => (int) env('BROADCAST_MAX_DELAY_SECONDS', 45),
        'stop_error_rate' => (int) env('BROADCAST_STOP_ERROR_RATE', 35),
    ],
];
