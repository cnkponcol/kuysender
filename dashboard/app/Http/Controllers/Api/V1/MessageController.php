<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Services\WaService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;

class MessageController extends BaseApiController
{
    public function send(Request $request, WaService $wa, WebhookDispatcher $webhooks)
    {
        $validated = $request->validate([
            'session_id' => ['required', 'uuid'],
            'receiver' => ['required', 'string', 'max:80'],
            'message_type' => ['required', 'in:text,media,button,list'],
            'data' => ['required', 'array'],
            'data.message' => ['nullable', 'string', 'max:10000'],
            'data.url' => ['required_if:message_type,media', 'nullable', 'url:http,https', 'max:2048'],
            'data.media_type' => ['required_if:message_type,media', 'nullable', 'in:image,video,audio,document'],
        ]);
        $session = $this->session($request, $validated['session_id']);
        $result = $wa->send($session->id, $validated['receiver'], $validated['message_type'], $validated['data']);
        $chatJid = $result['data']['chat_jid'] ?? $validated['receiver'];
        $message = Message::create([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'wa_message_id' => $result['data']['message_id'] ?? null,
            'chat_jid' => $chatJid,
            'direction' => 'outbound',
            'message_type' => $validated['message_type'],
            'body' => $validated['data']['message'] ?? $validated['data']['caption'] ?? null,
            'status' => 'sent',
            'is_read' => true,
            'metadata' => ['source' => 'api', 'api_client_id' => $this->client($request)->id],
            'message_at' => now(),
        ]);
        $webhooks->dispatch('message.sent', $session->id, ['message_id' => $message->id, 'chat_jid' => $chatJid, 'source' => 'api']);
        return response()->json(['status' => 'success', 'data' => ['message_id' => $message->id, 'wa_message_id' => $message->wa_message_id, 'chat_jid' => $chatJid]], 201);
    }
}
