<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Message;
use App\Models\Session;
use App\Services\GatewayLogger;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class InternalWaEventController extends Controller
{
    public function store(Request $request, WebhookDispatcher $webhooks)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:64'],
            'session_id' => ['required', 'uuid'],
            'data' => ['nullable', 'array'],
        ]);

        $session = Session::find($data['session_id']);
        if (!$session) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $event = $data['type'];
        $payload = $data['data'] ?? [];

        if ($event === 'device.qr') {
            $session->update([
                'status' => 'STOPPED',
                'connection_state' => 'qr',
                'qr_code' => $payload['qr_code'] ?? null,
                'qr_expires_at' => $payload['qr_expires_at'] ?? now()->addMinute(),
                'last_seen_at' => now(),
                'last_error' => null,
            ]);
        } elseif ($event === 'device.connected') {
            $session->update([
                'status' => 'CONNECTED',
                'connection_state' => 'connected',
                'whatsapp_number' => $payload['whatsapp_number'] ?? $session->whatsapp_number,
                'qr_code' => null,
                'qr_expires_at' => null,
                'last_seen_at' => now(),
                'last_error' => null,
            ]);
            $webhooks->dispatch('device.connected', $session->id, [
                'whatsapp_number' => $session->fresh()->whatsapp_number,
                'push_name' => $payload['push_name'] ?? null,
            ]);
        } elseif ($event === 'device.disconnected') {
            $loggedOut = (bool) ($payload['logged_out'] ?? false);
            $session->update([
                'status' => 'STOPPED',
                'connection_state' => 'disconnected',
                'whatsapp_number' => $loggedOut ? null : $session->whatsapp_number,
                'qr_code' => null,
                'qr_expires_at' => null,
                'last_seen_at' => now(),
                'last_error' => isset($payload['error']) ? mb_substr((string) $payload['error'], 0, 2000) : null,
            ]);
            $webhooks->dispatch('device.disconnected', $session->id, [
                'logged_out' => $loggedOut,
                'error' => $payload['error'] ?? null,
            ]);
        } elseif ($event === 'message.incoming') {
            ProcessIncomingMessage::dispatch($session->id, [
                'id' => $payload['wa_message_id'] ?? null,
                'chat_jid' => $payload['chat_jid'] ?? null,
                'reply_jid' => $payload['reply_jid'] ?? ($payload['chat_jid'] ?? null),
                'sender_jid' => $payload['sender_jid'] ?? null,
                'sender_phone_jid' => $payload['sender_phone_jid'] ?? null,
                'sender_lid' => $payload['sender_lid'] ?? null,
                'push_name' => $payload['sender_name'] ?? null,
                'type' => $payload['message_type'] ?? 'text',
                'body' => $payload['body'] ?? null,
                'message_at' => $payload['message_at'] ?? null,
                'metadata' => [
                    'is_group' => (bool) ($payload['is_group'] ?? false),
                    'raw_type' => $payload['raw_type'] ?? null,
                    'reply_jid' => $payload['reply_jid'] ?? null,
                    'sender_phone_jid' => $payload['sender_phone_jid'] ?? null,
                    'sender_lid' => $payload['sender_lid'] ?? null,
                ],
            ])->onQueue('messages');
        } elseif ($event === 'message.status') {
            if (!empty($payload['wa_message_id'])) {
                $status = (string) ($payload['status'] ?? 'updated');
                $updated = Message::where('session_id', $session->id)
                    ->where('wa_message_id', $payload['wa_message_id'])
                    ->update([
                        'status' => $status,
                        'is_read' => in_array($status, ['read', 'played'], true),
                    ]);

                if ($updated) {
                    $webhooks->dispatch('message.status', $session->id, [
                        'wa_message_id' => $payload['wa_message_id'],
                        'status' => $status,
                    ]);
                }
            }
        } else {
            GatewayLogger::write('wa_event', 'Unknown WA service event received.', ['event' => $event], 'warning', $session->user_id, $session->id);
        }

        return response()->json(['status' => 'ok']);
    }
}
