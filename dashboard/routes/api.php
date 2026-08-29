<?php

use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\InboxController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\InternalWaEventController;
use Illuminate\Support\Facades\Route;

Route::post('/internal/wa/events', [InternalWaEventController::class, 'store'])->middleware('internal.wa');

Route::prefix('v1')->middleware(['api.client', 'api.client.throttle', 'api.request.log'])->group(function () {
    Route::get('/devices', [DeviceController::class, 'index'])->middleware('api.scope:devices:read');
    Route::get('/devices/{sessionId}', [DeviceController::class, 'show'])->middleware('api.scope:devices:read');
    Route::post('/devices/{sessionId}/connect', [DeviceController::class, 'connect'])->middleware('api.scope:devices:manage');
    Route::post('/devices/{sessionId}/logout', [DeviceController::class, 'logout'])->middleware('api.scope:devices:manage');

    Route::post('/messages/send', [MessageController::class, 'send'])->middleware('api.scope:messages:send');

    Route::get('/contacts', [ContactController::class, 'index'])->middleware('api.scope:contacts:read');
    Route::post('/contacts', [ContactController::class, 'store'])->middleware('api.scope:contacts:write');
    Route::post('/contacts/{contactId}/opt-out', [ContactController::class, 'optOut'])->middleware('api.scope:contacts:write');

    Route::get('/inbox', [InboxController::class, 'chats'])->middleware('api.scope:inbox:read');
    Route::get('/inbox/{sessionId}/{chatJid}', [InboxController::class, 'messages'])->middleware('api.scope:inbox:read');
    Route::post('/inbox/{sessionId}/{chatJid}/reply', [InboxController::class, 'reply'])->middleware('api.scope:inbox:reply');
});
