<?php

namespace App\Jobs;

use App\Models\AiSetting;
use App\Models\AutoResponder;
use App\Models\Contact;
use App\Models\ContactLabel;
use App\Models\Message;
use App\Models\Session;
use App\Services\AiAssistantService;
use App\Services\GatewayLogger;
use App\Services\WaService;
use App\Services\WebhookDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $sessionId, public array $data) {}

    public function handle(WaService $wa, WebhookDispatcher $webhooks, AiAssistantService $ai): void
    {
        $session = Session::find($this->sessionId);
        if (!$session) return;

        $chatJid = (string) ($this->data['chat_jid'] ?? '');
        $senderJid = (string) ($this->data['sender_jid'] ?? $chatJid);
        $senderPhoneJid = (string) ($this->data['sender_phone_jid'] ?? '');
        $replyJid = (string) ($this->data['reply_jid'] ?? $chatJid);
        if ($chatJid === '' || $senderJid === '') return;
        if ($replyJid === '') $replyJid = $chatJid;

        $messageAt = !empty($this->data['message_at'])
            ? \Illuminate\Support\Carbon::parse($this->data['message_at'])->setTimezone(config('app.timezone'))
            : (!empty($this->data['timestamp']) ? now()->setTimestamp((int) $this->data['timestamp']) : now());

        $messageAttributes = [
            'id' => (string) Str::uuid(),
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'wa_message_id' => $this->data['id'] ?? null,
            'chat_jid' => $chatJid,
            'sender_jid' => $senderJid,
            'sender_name' => $this->data['push_name'] ?? null,
            'direction' => 'inbound',
            'message_type' => $this->data['type'] ?? 'text',
            'body' => $this->data['body'] ?? null,
            'media_url' => $this->data['media_url'] ?? null,
            'media_mime' => $this->data['media_mime'] ?? null,
            'status' => 'received',
            'is_read' => false,
            'metadata' => $this->data['metadata'] ?? null,
            'message_at' => $messageAt,
        ];

        $waMessageId = $messageAttributes['wa_message_id'];
        if ($waMessageId) {
            $message = Message::firstOrCreate(
                ['session_id' => $session->id, 'wa_message_id' => $waMessageId, 'direction' => 'inbound'],
                $messageAttributes
            );
            // Idempotency: duplicate WA events must never trigger a second webhook/autoreply/AI response.
            if (!$message->wasRecentlyCreated) {
                return;
            }
        } else {
            // A missing upstream id is unusual, but must not collapse unrelated messages into one NULL record.
            $message = Message::create($messageAttributes);
        }

        $isGroup = str_ends_with($chatJid, '@g.us');
        $numberSource = $senderPhoneJid !== '' ? $senderPhoneJid : $senderJid;
        $number = preg_replace('/\D+/', '', explode('@', $numberSource)[0]);
        $label = ContactLabel::firstOrCreate([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'title' => 'Auto Contacts',
        ]);

        $contact = Contact::where('user_id', $session->user_id)
            ->where('session_id', $session->id)
            ->where(function ($query) use ($senderJid, $number) {
                $query->where('wa_jid', $senderJid)->orWhere('number', $number);
            })->first();
        if (!$contact) {
            $contact = Contact::create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'label_id' => $label->id,
                'name' => $this->data['push_name'] ?? null,
                'profile_name' => $this->data['push_name'] ?? null,
                'number' => $number,
                'wa_jid' => $senderJid,
                'opt_in_status' => 'unknown',
                'first_chat_at' => $messageAt,
            ]);
        }
        $contact->wa_jid = $senderJid;
        if ($senderPhoneJid !== '' && $number !== '') $contact->number = $number;
        $contact->profile_name = $this->data['push_name'] ?? $contact->profile_name;
        if (!$contact->name && !empty($this->data['push_name'])) $contact->name = $this->data['push_name'];
        $contact->last_chat_at = $messageAt;
        $contact->save();

        $body = trim((string) ($message->body ?? ''));
        if ($body !== '' && in_array(mb_strtoupper($body), ['STOP', 'BERHENTI', 'UNSUBSCRIBE', 'UNSUB'], true)) {
            $contact->update([
                'opt_in_status' => 'opted_out',
                'opted_out_at' => now(),
            ]);
        }

        $webhooks->dispatch('message.received', $session->id, [
            'message_id' => $message->id,
            'wa_message_id' => $message->wa_message_id,
            'chat_jid' => $chatJid,
            'sender_jid' => $senderJid,
            'sender_name' => $message->sender_name,
            'type' => $message->message_type,
            'body' => $message->body,
            'message_at' => $messageAt->toIso8601String(),
        ]);

        if ($body === '') return;

        // AutoResponder tetap aktif; human takeover hanya mem-pause AI assistant.
        if ($this->runAutoresponder($session, $chatJid, $replyJid, $body, $isGroup, $wa, $webhooks)) {
            return;
        }

        $setting = AiSetting::where('session_id', $session->id)->first();
        if (!$setting || !$ai->shouldRun($setting, $contact)) return;

        try {
            $reply = $ai->generate($setting, $session->id, $chatJid);
            if ($setting->mode === 'suggest') {
                $message->update(['ai_suggestion' => $reply]);
                $webhooks->dispatch('ai.suggestion', $session->id, ['message_id' => $message->id, 'suggestion' => $reply]);
                return;
            }

            $result = $wa->send($session->id, $replyJid, 'text', ['message' => $reply]);
            $outbound = Message::create([
                'user_id' => $session->user_id,
                'session_id' => $session->id,
                'wa_message_id' => $result['data']['message_id'] ?? null,
                'chat_jid' => $chatJid,
                'sender_jid' => null,
                'sender_name' => 'Admin Kuskuskuy',
                'direction' => 'outbound',
                'message_type' => 'text',
                'body' => $reply,
                'status' => 'sent',
                'is_read' => true,
                'metadata' => ['source' => 'ai', 'delivery_jid' => $replyJid],
                'message_at' => now(),
            ]);
            $webhooks->dispatch('message.sent', $session->id, ['message_id' => $outbound->id, 'chat_jid' => $chatJid, 'source' => 'ai']);
        } catch (\Throwable $e) {
            GatewayLogger::write('ai', 'AI reply failed.', ['error' => $e->getMessage()], 'error', $session->user_id, $session->id);
        }
    }

    private function runAutoresponder(Session $session, string $chatJid, string $replyJid, string $body, bool $isGroup, WaService $wa, WebhookDispatcher $webhooks): bool
    {
        $responders = AutoResponder::where('user_id', $session->user_id)
            ->where('session_id', $session->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($responders as $row) {
            if ($row->reply_when === 'group' && !$isGroup) continue;
            if ($row->reply_when === 'personal' && $isGroup) continue;

            $needle = mb_strtolower(trim((string) $row->keyword));
            $haystack = mb_strtolower($body);
            $matched = $row->type_keyword === 'contains'
                ? str_contains($haystack, $needle)
                : $haystack === $needle;
            if (!$matched) continue;

            $data = json_decode($row->message, true) ?: [];
            try {
                $result = $wa->send($session->id, $replyJid, $row->message_type, $data);
                $replyBody = (string) ($data['message'] ?? $data['caption'] ?? '');
                $outbound = Message::create([
                    'user_id' => $session->user_id,
                    'session_id' => $session->id,
                    'wa_message_id' => $result['data']['message_id'] ?? null,
                    'chat_jid' => $chatJid,
                    'sender_jid' => null,
                    'sender_name' => 'AutoResponder',
                    'direction' => 'outbound',
                    'message_type' => $row->message_type,
                    'body' => $replyBody !== '' ? $replyBody : '['.$row->message_type.']',
                    'status' => 'sent',
                    'is_read' => true,
                    'metadata' => ['source' => 'autoresponder', 'responder_id' => $row->id, 'delivery_jid' => $replyJid],
                    'message_at' => now(),
                ]);
                $webhooks->dispatch('message.sent', $session->id, [
                    'message_id' => $outbound->id,
                    'chat_jid' => $chatJid,
                    'source' => 'autoresponder',
                    'responder_id' => $row->id,
                ]);
                return true;
            } catch (\Throwable $e) {
                GatewayLogger::write('autoresponder', 'Auto responder failed.', ['responder_id' => $row->id, 'error' => $e->getMessage()], 'error', $session->user_id, $session->id);
                return false;
            }
        }
        return false;
    }
}
