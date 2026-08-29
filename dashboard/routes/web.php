<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AutoresponderController;
use App\Http\Controllers\CampaignsController;
use App\Http\Controllers\DashController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\PhonebookController;
use App\Http\Controllers\SingleSender;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashController::class, 'index'])->name('dashboard');
    Route::get('dashboard', [DashController::class, 'index'])->name('dashboard.home');
    Route::get('dashboard/data', [DashController::class, 'index'])->name('dashboard.data');
    Route::get('storage', [DashController::class, 'storage'])->name('storage');
    Route::get('files', [DashController::class, 'files'])->name('files');

    Route::prefix('ajax/device')->group(function () {
        Route::post('main', [DashController::class, 'ajax_main_device'])->name('ajax.main_device');
        Route::post('change-main', [DashController::class, 'ajax_change_device'])->name('ajax.change_device');
    });

    Route::prefix('device')->group(function () {
        Route::get('id/{id}', [DeviceController::class, 'index'])->name('device.detail');
        Route::post('{id}/start', [DeviceController::class, 'start'])->name('device.start');
        Route::get('{id}/status', [DeviceController::class, 'status'])->name('device.status');
        Route::post('{id}/logout', [DeviceController::class, 'logout'])->name('device.logout');
        Route::post('delete', [DashController::class, 'device_delete'])->name('device.delete');
        Route::post('store', [DashController::class, 'device_store'])->name('device.store');
    });

    Route::prefix('inbox')->group(function () {
        Route::get('/', [InboxController::class, 'index'])->name('inbox');
        Route::post('/reply', [InboxController::class, 'reply'])->name('inbox.reply');
        Route::post('/takeover', [InboxController::class, 'takeover'])->name('inbox.takeover');
    });

    Route::prefix('ai')->group(function () {
        Route::get('/', [AiController::class, 'index'])->name('ai.index');
        Route::post('/', [AiController::class, 'update'])->name('ai.update');
        Route::post('/prompt/clear', [AiController::class, 'clearPrompt'])->name('ai.prompt.clear');
        Route::post('/knowledge', [AiController::class, 'knowledgeStore'])->name('ai.knowledge.store');
        Route::put('/knowledge/{id}', [AiController::class, 'knowledgeUpdate'])->name('ai.knowledge.update');
        Route::post('/knowledge/{id}/toggle', [AiController::class, 'knowledgeToggle'])->name('ai.knowledge.toggle');
        Route::delete('/knowledge/{id}', [AiController::class, 'knowledgeDelete'])->name('ai.knowledge.delete');
        Route::get('/knowledge-export', [AiController::class, 'knowledgeExport'])->name('ai.knowledge.export');
        Route::post('/knowledge-import', [AiController::class, 'knowledgeImport'])->name('ai.knowledge.import');
    });

    Route::prefix('api-clients')->group(function () {
        Route::get('/', [ApiClientController::class, 'index'])->name('api.clients');
        Route::post('/', [ApiClientController::class, 'store'])->name('api.clients.store');
        Route::post('/{id}/rotate', [ApiClientController::class, 'rotate'])->name('api.clients.rotate');
        Route::post('/{id}/toggle', [ApiClientController::class, 'toggle'])->name('api.clients.toggle');
        Route::delete('/{id}', [ApiClientController::class, 'destroy'])->name('api.clients.destroy');
    });

    Route::prefix('responder')->group(function () {
        Route::get('/', [AutoresponderController::class, 'index'])->name('responder');
        Route::get('/data', [AutoresponderController::class, 'index'])->name('responder.data');
        Route::get('/detail/{id}', [AutoresponderController::class, 'detail'])->name('responder.detail');
        Route::post('/delete', [AutoresponderController::class, 'delete'])->name('responder.delete');
        Route::post('/store', [AutoresponderController::class, 'store'])->name('responder.store');
        Route::post('/update/{id}', [AutoresponderController::class, 'update'])->name('responder.update');
        Route::post('/status', [AutoresponderController::class, 'status'])->name('responder.status');
    });

    Route::prefix('message')->group(function () {
        Route::get('/', [SingleSender::class, 'index'])->name('single');
        Route::post('/store', [SingleSender::class, 'store'])->name('single.store');
    });

    Route::prefix('phonebook')->group(function () {
        Route::get('/', [PhonebookController::class, 'index'])->name('phonebook');
        Route::post('/delete/{id}', [PhonebookController::class, 'label_delete'])->name('phonebook.delete');
        Route::post('/ajax/storelabels', [PhonebookController::class, 'ajax_label_store'])->name('phonebook.ajax.label.store');
        Route::prefix('contacts/{id}')->group(function () {
            Route::get('/', [PhonebookController::class, 'contacts'])->name('phonebook.contacts.index');
            Route::get('/data', [PhonebookController::class, 'contacts'])->name('phonebook.contacts.ajax');
            Route::post('/store', [PhonebookController::class, 'contacts_store'])->name('phonebook.contacts.store');
            Route::post('/delete', [PhonebookController::class, 'contacts_delete'])->name('phonebook.contacts.delete');
            Route::get('/export', [PhonebookController::class, 'contacts_export'])->name('phonebook.contacts.export');
            Route::post('/import', [PhonebookController::class, 'contacts_import'])->name('phonebook.contacts.import');
            Route::post('/sync-whatsapp', [PhonebookController::class, 'sync_whatsapp_contacts'])->name('phonebook.contacts.syncwhatsapp');
            Route::post('/fetch-group', [PhonebookController::class, 'fetch_group'])->name('phonebook.contacts.fetchgroup');
        });
        Route::post('/contact/{id}/consent', [PhonebookController::class, 'consent'])->name('phonebook.contacts.consent');
    });

    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignsController::class, 'index'])->name('campaigns.index');
        Route::get('/data', [CampaignsController::class, 'index'])->name('campaigns.ajax');
        Route::get('/detail/{id}', [CampaignsController::class, 'detail'])->name('campaigns.detail');
        Route::get('/detail/{id}/data', [CampaignsController::class, 'detail'])->name('campaigns.detail.ajax');
        Route::post('/store', [CampaignsController::class, 'store'])->name('campaigns.store');
        Route::post('/delete', [CampaignsController::class, 'delete'])->name('campaigns.delete');
        Route::post('/status', [CampaignsController::class, 'ajax_change_status'])->name('campaigns.ajax.changestatus');
    });

    Route::get('apidocs', [ApiController::class, 'index'])->name('apidocs');
    Route::get('logs', [LogsController::class, 'index'])->name('logs.index');

    Route::prefix('admin')->middleware('isadmin')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/users/data', [AdminController::class, 'users'])->name('admin.users.ajax');
        Route::post('/users/store', [AdminController::class, 'users_store'])->name('admin.users.store')->middleware('isdemo');
        Route::get('/users/edit/{id}', [AdminController::class, 'users_edit'])->name('admin.users.edit');
        Route::post('/users/update', [AdminController::class, 'users_update'])->name('admin.users.update')->middleware('isdemo');
        Route::post('/users/delete/{id}', [AdminController::class, 'users_delete'])->name('admin.users.delete')->middleware('isdemo');
    });
});

require __DIR__.'/files.php';
