<?php

namespace App\Http\Controllers;

use App\Helpers\Lyn;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\GatewayLog;
use App\Models\Session;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) $request->user()->id;
        $sessionIds = Session::where('user_id', $userId)->pluck('id');
        $clientIds = ApiClient::where('user_id', $userId)->pluck('id');

        return Lyn::view('logs.index', [
            'gatewayLogs' => GatewayLog::where('user_id', $userId)->latest('created_at')->limit(100)->get(),
            'apiLogs' => ApiRequestLog::whereIn('api_client_id', $clientIds)->latest('created_at')->limit(100)->get(),
            'webhooks' => WebhookDelivery::whereIn('api_client_id', $clientIds)
                ->where(function ($q) use ($sessionIds) {
                    $q->whereNull('session_id')->orWhereIn('session_id', $sessionIds);
                })
                ->latest()
                ->limit(100)
                ->get(),
            'clients' => ApiClient::where('user_id', $userId)->pluck('name', 'id'),
        ]);
    }
}
