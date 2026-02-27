<?php

use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::post('/upload', [UploadController::class, 'store']);
Route::get('/upload/preview', [UploadController::class, 'preview']);
Route::post('/build/generate-step2', [BuildController::class, 'generateStep2']);
Route::post('/build', [BuildController::class, 'store']);
Route::get('/build/{buildId}', [BuildController::class, 'show']);
Route::get('/build/{buildId}/download/{type}', [BuildController::class, 'download'])
    ->where('type', 'apk|ipa|keystore');
