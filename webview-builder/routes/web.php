<?php

use App\Http\Controllers\Web\BuildController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('build.step1'));

Route::prefix('build')->name('build.')->group(function () {
    Route::get('/step1', [BuildController::class, 'step1'])->name('step1');
    Route::post('/step1', [BuildController::class, 'step1Store'])->name('step1.store');
    Route::get('/step2', [BuildController::class, 'step2'])->name('step2');
    Route::post('/step2', [BuildController::class, 'step2Store'])->name('step2.store');
    Route::get('/step3', [BuildController::class, 'step3'])->name('step3');
    Route::post('/step3', [BuildController::class, 'step3Store'])->name('step3.store');
    Route::get('/{id}', [BuildController::class, 'show'])->name('show');
});
