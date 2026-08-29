<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\WaService;
use Illuminate\Http\Request;

class DeviceController extends BaseApiController
{
    public function index(Request $request)
    {
        $devices = $this->client($request)->sessions()->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->session_name,
            'status' => $s->connection_state,
            'whatsapp_number' => $s->whatsapp_number,
            'last_seen_at' => optional($s->last_seen_at)->toIso8601String(),
        ]);
        return response()->json(['data' => $devices]);
    }

    public function show(Request $request, string $sessionId, WaService $wa)
    {
        $session = $this->session($request, $sessionId);
        try { $wa->deviceStatus($session->id); } catch (\Throwable) {}
        $session->refresh();
        $data = [
            'id' => $session->id,
            'name' => $session->session_name,
            'status' => $session->connection_state,
            'whatsapp_number' => $session->whatsapp_number,
            'last_seen_at' => optional($session->last_seen_at)->toIso8601String(),
            'last_error' => $session->last_error,
        ];

        // QR data is a credential-like artifact. Only clients trusted to manage devices may receive it.
        if ($this->client($request)->hasScope('devices:manage')) {
            $data['qr_code'] = $session->qr_code;
            $data['qr_expires_at'] = optional($session->qr_expires_at)->toIso8601String();
        }

        return response()->json(['data' => $data]);
    }

    public function connect(Request $request, string $sessionId, WaService $wa)
    {
        $session = $this->session($request, $sessionId);
        $result = $wa->connectDevice($session->id, $session->session_name);
        return response()->json($result);
    }

    public function logout(Request $request, string $sessionId, WaService $wa)
    {
        $session = $this->session($request, $sessionId);
        $result = $wa->logoutDevice($session->id);
        return response()->json($result);
    }
}
