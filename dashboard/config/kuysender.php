<?php

return [
    'cleanup' => [
        'gateway_log_days' => (int) env('KUYSENDER_GATEWAY_LOG_DAYS', 30),
        'api_request_log_days' => (int) env('KUYSENDER_API_REQUEST_LOG_DAYS', 30),
        'webhook_delivered_days' => (int) env('KUYSENDER_WEBHOOK_DELIVERED_DAYS', 14),
        'webhook_failed_days' => (int) env('KUYSENDER_WEBHOOK_FAILED_DAYS', 30),
        'failed_job_days' => (int) env('KUYSENDER_FAILED_JOB_DAYS', 30),
        'temp_file_hours' => (int) env('KUYSENDER_TEMP_FILE_HOURS', 48),
        // Inbox history is preserved by default. Set >0 only if an explicit retention policy is desired.
        'message_days' => (int) env('KUYSENDER_MESSAGE_RETENTION_DAYS', 0),
    ],
];
