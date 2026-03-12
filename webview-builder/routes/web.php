<?php

use App\Http\Controllers\Web\BuildController;
use App\Http\Controllers\Web\ChatController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\NewsDetailController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing.index');
Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
Route::get('/settings', [LandingController::class, 'settings'])->name('landing.settings');
Route::get('/news/detail', [NewsDetailController::class, 'show'])->name('news.detail');

Route::prefix('build')->name('build.')->group(function () {
    Route::get('/step1', [BuildController::class, 'step1'])->name('step1');
    Route::post('/step1', [BuildController::class, 'step1Store'])->name('step1.store');
    Route::get('/step2', [BuildController::class, 'step2'])->name('step2');
    Route::post('/step2', [BuildController::class, 'step2Store'])->name('step2.store');
    Route::get('/step3', [BuildController::class, 'step3'])->name('step3');
    Route::post('/step3', [BuildController::class, 'step3Store'])->name('step3.store');
    Route::get('/{id}', [BuildController::class, 'show'])->name('show');
});
