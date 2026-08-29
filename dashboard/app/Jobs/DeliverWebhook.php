<?php

namespace App\Jobs;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use App\Services\SafeOutboundUrl;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;
    public array $backoff = [10, 30, 120, 300, 900];

    public function __construct(public string $deliveryId)
    {
        $this->tries = (int) config('services.webhook.max_attempts', 5);
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if (!$delivery || $delivery->status === 'delivered') {
            return;
        }

        $client = ApiClient::find($delivery->api_client_id);
        if (!$client || !$client->is_active || !$client->webhook_url) {
            $delivery->update(['status' => 'disabled', 'last_error' => 'API client or webhook disabled.']);
            return;
        }

        SafeOutboundUrl::assert($client->webhook_url, app()->environment('production'));

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, (string) $client->webhook_secret);

        try {
            $response = Http::timeout((int) config('services.webhook.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'KuySender-Webhook/5.0',
                    'X-KuySender-Event' => $delivery->event_type,
                    'X-KuySender-Timestamp' => $timestamp,
                    'X-KuySender-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($client->webhook_url);

            $delivery->response_code = $response->status();

            if (!$response->successful()) {
                $delivery->status = 'retrying';
                $delivery->last_error = 'Webhook returned HTTP '.$response->status();
                $delivery->save();
                throw new RuntimeException($delivery->last_error);
            }

            $delivery->attempts++;
            $delivery->status = 'delivered';
            $delivery->delivered_at = now();
            $delivery->last_error = null;
            $delivery->save();
        } catch (\Throwable $e) {
            $delivery->attempts++;
            $delivery->status = $this->attempts() >= $this->tries ? 'failed' : 'retrying';
            $delivery->last_error = mb_substr($e->getMessage(), 0, 2000);
            $delivery->save();
            throw $e;
        }
    }
}
