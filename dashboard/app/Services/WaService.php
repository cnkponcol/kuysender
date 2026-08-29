<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WaService
{
    private function client(): PendingRequest
    {
        $token = (string) config('services.wa.internal_token');
        if ($token === '') {
            throw new RuntimeException('WA_INTERNAL_TOKEN is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.wa.url'), '/'))
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.wa.timeout', 20))
            ->retry(2, 250, throw: false);
    }

    private function jsonResponse($response): array
    {
        try {
            $response->throw();
        } catch (RequestException $e) {
            $message = $response->json('message') ?: 'WA service request failed.';
            throw new RuntimeException($message, $response->status(), $e);
        }
        return $response->json() ?: ['status' => true];
    }
    private function ensureConnected(string $sessionId, int $attempts = 16): void
    {
        $status = $this->deviceStatus($sessionId);
        $state = (string) ($status['data']['connection_state'] ?? 'disconnected');
        if ($state === 'connected') return;
        if ($state === 'qr') throw new RuntimeException('WhatsApp needs QR scan before sending.');

        $this->jsonResponse($this->client()->post(
            '/internal/devices/'.urlencode($sessionId).'/connect', []
        ));

        for ($i = 0; $i < $attempts; $i++) {
            usleep(500000);
            $status = $this->deviceStatus($sessionId);
            $state = (string) ($status['data']['connection_state'] ?? 'disconnected');
            if ($state === 'connected') return;
            if ($state === 'qr') throw new RuntimeException('WhatsApp needs QR scan before sending.');
        }

        throw new RuntimeException('WhatsApp is reconnecting. Please retry in a few seconds.', 503);
    }

    public function health(): array
    {
        return $this->jsonResponse($this->client()->get('/health'));
    }

    public function connectDevice(string $sessionId, string $name): array
    {
        return $this->jsonResponse($this->client()->post('/internal/devices/'.urlencode($sessionId).'/connect', [
            'name' => $name,
        ]));
    }

    public function deviceStatus(string $sessionId): array
    {
        return $this->jsonResponse($this->client()->get('/internal/devices/'.urlencode($sessionId)));
    }
    public function logoutDevice(string $sessionId): array
    {
        return $this->jsonResponse($this->client()->post('/internal/devices/'.urlencode($sessionId).'/logout'));
    }

    public function deleteDevice(string $sessionId): array
    {
        return $this->jsonResponse($this->client()->delete('/internal/devices/'.urlencode($sessionId)));
    }

    public function send(string $sessionId, string $receiver, string $messageType, array $data): array
    {
        $this->ensureConnected($sessionId);

        if ($messageType === 'media' && !empty($data['url'])) {
            $parts = parse_url((string) $data['url']);
            $storagePath = null;
            if (($parts['path'] ?? '') === parse_url(route('storage'), PHP_URL_PATH) && !empty($parts['query'])) {
                parse_str($parts['query'], $query);
                $storagePath = isset($query['url'])
                    ? ltrim(str_replace('\\', '/', urldecode((string) $query['url'])), '/')
                    : null;
            }
            if ($storagePath && !str_contains($storagePath, '..') && Storage::exists($storagePath)) {
                $data['local_path'] = Storage::path($storagePath);
                unset($data['url']);
            }
        }

        return $this->jsonResponse($this->client()->post('/internal/messages/send', [
            'session_id' => $sessionId,
            'receiver' => $receiver,
            'message_type' => $messageType,
            'data' => $data,
        ]));
    }
    public function contacts(string $sessionId): array
    {
        return $this->jsonResponse($this->client()->get('/internal/devices/'.urlencode($sessionId).'/contacts'));
    }

    public function groups(string $sessionId): array
    {
        return $this->jsonResponse($this->client()->get('/internal/devices/'.urlencode($sessionId).'/groups'));
    }

    public function groupMembers(string $sessionId, string $groupId): array
    {
        return $this->jsonResponse($this->client()->get(
            '/internal/devices/'.urlencode($sessionId).'/groups/'.urlencode($groupId).'/members'
        ));
    }
}
