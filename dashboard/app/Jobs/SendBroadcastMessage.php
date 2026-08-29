<?php

namespace App\Jobs;

use App\Models\Bulk;
use App\Models\Campaigns;
use App\Models\Contact;
use App\Models\Message;
use App\Services\GatewayLogger;
use App\Services\WaService;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBroadcastMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public string $bulkId) {}

    public function handle(WaService $wa, WebhookDispatcher $webhooks): void
    {
        $bulk = Bulk::find($this->bulkId);
        if (!$bulk || $bulk->status !== 'pending') return;
        $campaign = Campaigns::find($bulk->campaign_id);
        if (!$campaign || !in_array($campaign->status, ['waiting', 'processing'], true)) {
            if ($bulk) $bulk->update(['next_attempt_at' => null]);
            return;
        }

        $contact = $bulk->contact_id ? Contact::find($bulk->contact_id) : Contact::where('session_id', $bulk->session_id)->where('number', $bulk->receiver)->first();
        if ($campaign->opt_in_only && (!$contact || !$contact->isBroadcastEligible())) {
            $bulk->update(['status' => 'invalid', 'next_attempt_at' => null, 'error_message' => 'Contact is not opted in or is blocklisted.']);
            $campaign->increment('processed_count');
            return;
        }

        try {
            $payload = json_decode($bulk->message, true) ?: [];
            $result = $wa->send($bulk->session_id, $bulk->receiver, $bulk->message_type, $payload);
            $bulk->update([
                'status' => 'sent',
                'attempts' => $bulk->attempts + 1,
                'next_attempt_at' => null,
                'sent_at' => now(),
                'error_message' => null,
            ]);
            $campaign->increment('processed_count');

            Message::create([
                'user_id' => $bulk->user_id,
                'session_id' => $bulk->session_id,
                'wa_message_id' => $result['data']['message_id'] ?? null,
                'chat_jid' => $bulk->receiver,
                'direction' => 'outbound',
                'message_type' => $bulk->message_type,
                'body' => $payload['message'] ?? $payload['caption'] ?? null,
                'status' => 'sent',
                'is_read' => true,
                'metadata' => ['source' => 'broadcast', 'campaign_id' => $campaign->id],
                'message_at' => now(),
            ]);
            $webhooks->dispatch('broadcast.sent', $bulk->session_id, ['campaign_id' => $campaign->id, 'receiver' => $bulk->receiver]);
        } catch (\Throwable $e) {
            $attempts = $bulk->attempts + 1;
            $retry = $attempts < 3;
            $bulk->update([
                'status' => $retry ? 'pending' : 'failed',
                'attempts' => $attempts,
                'next_attempt_at' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            if (!$retry) {
                $campaign->increment('processed_count');
                $campaign->increment('error_count');
            }
            GatewayLogger::write('broadcast', 'Broadcast send failed.', ['bulk_id' => $bulk->id, 'error' => $e->getMessage()], 'error', $bulk->user_id, $bulk->session_id);
        }
    }
}
