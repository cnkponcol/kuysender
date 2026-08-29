<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\Message;
use App\Models\Session;
use App\Services\WaService;
use Illuminate\Http\Request;

class SingleSender extends Controller
{
    public function index()
    {
        if (!session('main_device')) return Lyn::view('nodevice');
        return Lyn::view('singlesend.index');
    }

    public function store(Request $request, WaService $wa)
    {
        $sessionId = session('main_device');
        if (!$sessionId) return response()->json(['message' => 'No device selected.'], 422);
        $device = Session::where('id', $sessionId)->where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'receiver' => ['required', 'string', 'max:80'],
            'message_type' => ['required', 'in:text,media,button,list'],
            'message' => ['nullable', 'string', 'max:10000'],
            'media' => ['nullable', 'url:http,https', 'max:2048'],
            'media_type' => ['nullable', 'in:image,video,audio,document'],
            'footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = ['message' => $data['message'] ?? ''];
        if ($data['message_type'] === 'media') {
            if (empty($data['media']) || empty($data['media_type'])) return response()->json(['message' => 'Media URL and media type are required.'], 422);
            $payload = ['url' => $data['media'], 'media_type' => $data['media_type'], 'caption' => $data['message'] ?? ''];
        } elseif ($data['message_type'] === 'button') {
            $buttons = [];
            foreach ((array) $request->input('btn_display', []) as $key => $label) $buttons[] = ['display' => $label, 'id' => $request->input("btn_id.$key")];
            $payload = ['message' => $data['message'] ?? '', 'footer' => $data['footer'] ?? '', 'buttons' => $buttons];
        } elseif ($data['message_type'] === 'list') {
            $sections = [['title' => $request->input('title', ''), 'rows' => []]];
            foreach ((array) $request->input('btn_display', []) as $key => $label) if ($request->input("type.$key") === 'option') $sections[0]['rows'][] = ['title' => $label, 'rowId' => $request->input("btn_id.$key", '')];
            $payload = ['title' => $request->input('title', ''), 'message' => $data['message'] ?? '', 'footer' => $data['footer'] ?? '', 'sections' => $sections];
        }

        try {
            $result = $wa->send($device->id, $data['receiver'], $data['message_type'], $payload);
            Message::create([
                'user_id' => $device->user_id, 'session_id' => $device->id,
                'wa_message_id' => $result['data']['message_id'] ?? null,
                'chat_jid' => $result['data']['chat_jid'] ?? $data['receiver'],
                'direction' => 'outbound', 'message_type' => $data['message_type'],
                'body' => $payload['message'] ?? $payload['caption'] ?? null,
                'status' => 'sent', 'is_read' => true, 'metadata' => ['source' => 'single_sender'], 'message_at' => now(),
            ]);
            return response()->json(['message' => 'Message sent.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
