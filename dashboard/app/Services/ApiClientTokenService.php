<?php

namespace App\Services;

use App\Models\ApiClient;
use Illuminate\Support\Str;

class ApiClientTokenService
{
    public function create(int $userId, string $name, array $scopes, int $rateLimit, ?string $webhookUrl, array $sessionIds): array
    {
        $keyId = Str::lower(Str::random(16));
        $secret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $token = 'kuy_'.$keyId.'.'.$secret;
        $webhookSecret = bin2hex(random_bytes(32));

        $client = ApiClient::create([
            'user_id' => $userId,
            'name' => $name,
            'key_id' => $keyId,
            'secret_hash' => hash('sha256', $secret),
            'secret_value' => $token,
            'scopes' => array_values(array_unique($scopes)),
            'rate_limit' => max(1, min(1000, $rateLimit)),
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookSecret,
            'is_active' => true,
        ]);

        if ($sessionIds !== []) {
            $client->sessions()->sync($sessionIds);
        }

        return ['client' => $client, 'token' => $token, 'webhook_secret' => $webhookSecret];
    }

    public function rotate(ApiClient $client): array
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $token = 'kuy_'.$client->key_id.'.'.$secret;
        $client->secret_hash = hash('sha256', $secret);
        $client->secret_value = $token;
        $client->save();

        return ['token' => $token];
    }
}
