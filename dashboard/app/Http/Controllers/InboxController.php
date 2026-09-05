<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Session;
use App\Services\WaService;
use Illuminate\Http\Request;
use RuntimeException;

class InboxController extends Controller
{
    private function main(Request $request): Session
    {
        $id = session('main_device');
        abort_unless($id, 404, 'Select a device first.');
        return Session::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request)
    {
        $session = $this->main($request);
        $chatIds = Message::where('session_id', $session->id)
            ->selectRaw('chat_jid, MAX(message_at) AS last_message_at')
            ->groupBy('chat_jid')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();
        $chats = $chatIds->map(function ($row) use ($session) {
            $latest = Message::where('session_id', $session->id)
                ->where('chat_jid', $row->chat_jid)
                ->orderByDesc('message_at')->first();
            $number = preg_replace('/\D+/', '', explode('@', $row->chat_jid)[0]);
            $contact = Contact::where('session_id', $session->id)
                ->where(function ($query) use ($row, $number) {
                    $query->where('wa_jid', $row->chat_jid)->orWhere('number', $number);
                })->first();

            return (object) [
                'jid' => $row->chat_jid,
                'name' => $contact?->name ?: $contact?->profile_name ?: $latest?->sender_name ?: $number,
                'last_body' => $latest?->body,
                'last_message_at' => $latest?->message_at,
                'unread' => Message::where('session_id', $session->id)
                    ->where('chat_jid', $row->chat_jid)
                    ->where('direction', 'inbound')
                    ->where('is_read', false)->count(),
                'contact' => $contact,
            ];
        });

        $selectedJid = $request->query('chat');
        $messages = collect();
        $selectedContact = null;
        if ($selectedJid) {
            Message::where('session_id', $session->id)
                ->where('chat_jid', $selectedJid)
                ->where('direction', 'inbound')
                ->update(['is_read' => true]);
            $messages = Message::where('session_id', $session->id)
                ->where('chat_jid', $selectedJid)
                ->orderBy('message_at')->limit(300)->get();
            $number = preg_replace('/\D+/', '', explode('@', $selectedJid)[0]);
            $selectedContact = Contact::where('session_id', $session->id)
                ->where(function ($query) use ($selectedJid, $number) {
                    $query->where('wa_jid', $selectedJid)->orWhere('number', $number);
                })->first();
        }

        return Lyn::view('inbox.index', compact(
            'session', 'chats', 'selectedJid', 'messages', 'selectedContact'
        ));
    }
    public function reply(Request $request, WaService $wa)
    {
        $session = $this->main($request);
        $data = $request->validate([
            'chat_jid' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        $number = preg_replace('/\D+/', '', explode('@', $data['chat_jid'])[0]);
        $contact = Contact::where('session_id', $session->id)
            ->where(function ($query) use ($data, $number) {
                $query->where('wa_jid', $data['chat_jid'])->orWhere('number', $number);
            })->first();
        $deliveryJid = $contact?->deliveryAddress() ?: $data['chat_jid'];

        try {
            $result = $wa->send($session->id, $deliveryJid, 'text', [
                'message' => $data['message'],
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors($e->getMessage())->withInput();
        }

        Message::create([
            'user_id' => $session->user_id,
            'session_id' => $session->id,
            'wa_message_id' => $result['data']['message_id'] ?? null,
            'chat_jid' => $data['chat_jid'],
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => $data['message'],
            'status' => 'sent',
            'is_read' => true,
            'metadata' => ['source' => 'dashboard', 'delivery_jid' => $deliveryJid],
            'message_at' => now(),
        ]);

        if ($contact) $contact->update(['human_takeover' => true]);

        return back()->with('success', 'Reply sent.');
    }
    public function deleteChat(Request $request)
    {
        $session = $this->main($request);
        $data = $request->validate([
            'chat_jid' => ['required', 'string', 'max:100'],
        ]);

        $deleted = Message::where('user_id', $request->user()->id)
            ->where('session_id', $session->id)
            ->where('chat_jid', $data['chat_jid'])
            ->delete();

        return redirect()->route('inbox')->with(
            'success',
            $deleted > 0 ? 'Chat berhasil dihapus dari Inbox KuySender.' : 'Chat sudah kosong.'
        );
    }

    public function takeover(Request $request)
    {
        $session = $this->main($request);
        $data = $request->validate([
            'chat_jid' => ['required', 'string', 'max:100'],
            'enabled' => ['required', 'boolean'],
        ]);
        $number = preg_replace('/\D+/', '', explode('@', $data['chat_jid'])[0]);
        Contact::where('session_id', $session->id)
            ->where(function ($query) use ($data, $number) {
                $query->where('wa_jid', $data['chat_jid'])->orWhere('number', $number);
            })->update(['human_takeover' => $data['enabled']]);

        return back()->with('success', $data['enabled']
            ? 'Human takeover enabled.'
            : 'Automatic replies may run again.');
    }
}
