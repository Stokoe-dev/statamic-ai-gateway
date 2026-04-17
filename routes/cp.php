<?php

use Illuminate\Support\Facades\Route;
use Stokoe\AiGateway\Http\Controllers\SettingsController;

Route::prefix('ai-gateway')->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('ai-gateway.settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('ai-gateway.settings.update');
});
