<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Message;
use App\Services\WaService;
use Illuminate\Http\Request;

class InboxController extends BaseApiController
{
    public function chats(Request $request)
    {
        $data = $request->validate(['session_id' => ['required', 'uuid']]);
        $session = $this->session($request, $data['session_id']);
        $rows = Message::where('session_id', $session->id)
            ->selectRaw('chat_jid, MAX(message_at) AS last_message_at, SUM(CASE WHEN direction = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count', ['inbound'])
            ->groupBy('chat_jid')->orderByDesc('last_message_at')->limit(200)->get();
        return response()->json(['data' => $rows]);
    }

    public function messages(Request $request, string $sessionId, string $chatJid)
    {
        $session = $this->session($request, $sessionId);
        $jid = urldecode($chatJid);
        Message::where('session_id', $session->id)->where('chat_jid', $jid)->where('direction', 'inbound')->update(['is_read' => true]);
        $messages = Message::where('session_id', $session->id)->where('chat_jid', $jid)->orderByDesc('message_at')->paginate(100);
        return response()->json($messages);
    }

    public function reply(Request $request, string $sessionId, string $chatJid, WaService $wa)
    {
        $session = $this->session($request, $sessionId);
        $data = $request->validate(['message' => ['required', 'string', 'max:10000']]);
        $jid = urldecode($chatJid);
        $result = $wa->send($session->id, $jid, 'text', ['message' => $data['message']]);
        $message = Message::create([
            'user_id' => $session->user_id, 'session_id' => $session->id,
            'wa_message_id' => $result['data']['message_id'] ?? null,
            'chat_jid' => $jid, 'direction' => 'outbound', 'message_type' => 'text',
            'body' => $data['message'], 'status' => 'sent', 'is_read' => true,
            'metadata' => ['source' => 'api_inbox'], 'message_at' => now(),
        ]);
        return response()->json(['data' => $message], 201);
    }
}
