<?php

use Illuminate\Support\Facades\Route;
use Stokoe\AiGateway\Http\Controllers\ToolExecutionController;
use Stokoe\AiGateway\Http\Middleware\AuthenticateGateway;
use Stokoe\AiGateway\Http\Middleware\EnforceRateLimit;

Route::prefix('ai-gateway')
    ->middleware([AuthenticateGateway::class, EnforceRateLimit::class])
    ->group(function () {
        Route::post('/execute', [ToolExecutionController::class, 'execute']);
        Route::get('/capabilities', [ToolExecutionController::class, 'capabilities']);
        Route::get('/capabilities/custom-commands', [ToolExecutionController::class, 'customCommands']);
        Route::get('/capabilities/{tool}', [ToolExecutionController::class, 'toolUsage'])
            ->where('tool', '[a-z]+\.[a-z]+');
    });
