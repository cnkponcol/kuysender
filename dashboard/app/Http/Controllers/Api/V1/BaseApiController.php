<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\Session;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    protected function client(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    protected function session(Request $request, string $sessionId): Session
    {
        $client = $this->client($request);
        $session = $client->sessions()->where('sessions.id', $sessionId)->first();
        abort_unless($session, 403, 'This API client is not allowed to use that device.');
        return $session;
    }
}
