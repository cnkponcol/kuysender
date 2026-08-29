<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\ApiClient;
use App\Models\WebhookDelivery;

class WebhookDispatcher
{
    public function dispatch(string $event, string $sessionId, array $data): void
    {
        $clients = ApiClient::query()
            ->where('is_active', true)
            ->whereNotNull('webhook_url')
            ->whereHas('sessions', fn ($q) => $q->where('sessions.id', $sessionId))
            ->get();

        foreach ($clients as $client) {
            $payload = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'event' => $event,
                'session_id' => $sessionId,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ];

            $delivery = WebhookDelivery::create([
                'api_client_id' => $client->id,
                'session_id' => $sessionId,
                'event_type' => $event,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            DeliverWebhook::dispatch($delivery->id)->onQueue('webhooks');
        }
    }
}
