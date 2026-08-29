<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\ApiClient;
use App\Models\Session;
use App\Services\ApiClientTokenService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiClientController extends Controller
{
    private const SCOPES = [
        'messages:send', 'devices:read', 'devices:manage', 'contacts:read', 'contacts:write', 'inbox:read', 'inbox:reply',
    ];

    public function index(Request $request)
    {
        return Lyn::view('api.clients', [
            'clients' => ApiClient::where('user_id', $request->user()->id)->with('sessions')->latest()->get(),
            'devices' => Session::where('user_id', $request->user()->id)->orderBy('session_name')->get(),
            'scopes' => self::SCOPES,
        ]);
    }

    public function store(Request $request, ApiClientTokenService $tokens)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => [Rule::in(self::SCOPES)],
            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*' => ['uuid'],
            'rate_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'webhook_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $owned = Session::where('user_id', $request->user()->id)->whereIn('id', $data['sessions'])->pluck('id')->all();
        abort_unless(count($owned) === count(array_unique($data['sessions'])), 422, 'One or more devices are invalid.');
        $created = $tokens->create($request->user()->id, $data['name'], $data['scopes'], $data['rate_limit'], $data['webhook_url'] ?: null, $owned);
        return redirect()->route('api.clients')->with('api_token', $created['token'])->with('webhook_secret', $created['webhook_secret'])->with('success', 'API client created. Copy the credentials now.');
    }

    public function rotate(Request $request, string $id, ApiClientTokenService $tokens)
    {
        $client = ApiClient::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $rotated = $tokens->rotate($client);
        return redirect()->route('api.clients')->with('api_token', $rotated['token'])->with('success', 'API token rotated. The old token is no longer valid.');
    }

    public function toggle(Request $request, string $id)
    {
        $client = ApiClient::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $client->update(['is_active' => !$client->is_active]);
        return back()->with('success', 'API client status updated.');
    }

    public function destroy(Request $request, string $id)
    {
        $client = ApiClient::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();
        $client->sessions()->detach();
        $client->delete();
        return back()->with('success', 'API client deleted.');
    }
}
