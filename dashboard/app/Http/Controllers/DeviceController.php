<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\Session;
use App\Services\WaService;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    private function owned(Request $request, string $id): Session
    {
        return Session::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
    }

    public function index(Request $request, string $id)
    {
        return Lyn::view('dash.device_detail', ['device' => $this->owned($request, $id)]);
    }

    public function start(Request $request, string $id, WaService $wa)
    {
        $device = $this->owned($request, $id);
        try {
            $result = $wa->connectDevice($device->id, $device->session_name);
            return response()->json(['message' => 'Session starting.', 'data' => $result['data'] ?? $result]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function status(Request $request, string $id, WaService $wa)
    {
        $device = $this->owned($request, $id);
        try {
            $result = $wa->deviceStatus($device->id);
            $live = $result['data'] ?? $result;

            $state = $live['connection_state'] ?? null;

            if (in_array($state, ['connected', 'connecting', 'qr', 'disconnected'], true)) {
                $updates = [
                    'connection_state' => $state,
                    'status' => $state === 'connected' ? 'CONNECTED' : 'STOPPED',
                    'last_seen_at' => now(),
                ];

                if (array_key_exists('whatsapp_number', $live) && $live['whatsapp_number']) {
                    $updates['whatsapp_number'] = $live['whatsapp_number'];
                }

                if ($state === 'connected') {
                    $updates['qr_code'] = null;
                    $updates['qr_expires_at'] = null;
                    $updates['last_error'] = null;
                }

                if ($state === 'qr') {
                    $updates['qr_code'] = $live['qr_code'] ?? $device->qr_code;
                    $updates['qr_expires_at'] = $live['qr_expires_at'] ?? $device->qr_expires_at;
                }

                $device->update($updates);
            }
        } catch (\Throwable) {
            // Keep the last known DB state if the WA service itself is unreachable.
        }

        $device->refresh();
        return response()->json(['data' => [
            'id' => $device->id,
            'name' => $device->session_name,
            'connection_state' => $device->connection_state,
            'status' => $device->status,
            'whatsapp_number' => $device->whatsapp_number,
            'qr_code' => $device->qr_code,
            'qr_expires_at' => optional($device->qr_expires_at)->toIso8601String(),
            'last_seen_at' => optional($device->last_seen_at)->toIso8601String(),
            'last_error' => $device->last_error,
        ]]);
    }

    public function logout(Request $request, string $id, WaService $wa)
    {
        $device = $this->owned($request, $id);
        try { $wa->logoutDevice($device->id); } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
        $device->update([
            'status' => 'STOPPED',
            'connection_state' => 'disconnected',
            'whatsapp_number' => null,
            'qr_code' => null,
            'qr_expires_at' => null,
            'last_error' => null,
        ]);
        return response()->json(['message' => 'WhatsApp session logged out.']);
    }
}
