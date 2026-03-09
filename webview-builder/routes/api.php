<?php

use App\Http\Controllers\Api\BuildController;
use App\Http\Controllers\Api\LandingNewsController;
use App\Http\Controllers\Api\LandingSettingsController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/landing/news', [LandingNewsController::class, 'index']);
Route::get('/landing/news/counts', [LandingNewsController::class, 'counts']);
Route::get('/landing/settings', [LandingSettingsController::class, 'show']);
Route::post('/landing/settings/logo', [LandingSettingsController::class, 'updateLogo']);
Route::post('/landing/features', [LandingSettingsController::class, 'storeFeature']);
Route::put('/landing/features/{id}', [LandingSettingsController::class, 'updateFeature']);
Route::delete('/landing/features/{id}', [LandingSettingsController::class, 'destroyFeature']);

Route::post('/upload', [UploadController::class, 'store']);
Route::get('/upload/preview', [UploadController::class, 'preview']);
Route::post('/build/generate-step2', [BuildController::class, 'generateStep2']);
Route::post('/build', [BuildController::class, 'store']);
Route::get('/build/{buildId}', [BuildController::class, 'show']);
Route::get('/build/{buildId}/download/{type}', [BuildController::class, 'download'])
    ->where('type', 'apk|ipa|keystore');
