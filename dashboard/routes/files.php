<?php

use App\Http\Controllers\FileManager;
use Illuminate\Support\Facades\Route;

Route::prefix('ilsya/files')->middleware('auth')->group(function () {
    Route::get('/', [FileManager::class, 'index'])->name('ilsya.files.index');
    Route::post('/upload', [FileManager::class, 'upload'])->name('ilsya.files.upload')->middleware('isdemo');
    Route::post('/delete', [FileManager::class, 'delete'])->name('ilsya.files.delete')->middleware('isdemo');
});
