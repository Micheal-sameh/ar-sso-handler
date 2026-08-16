<?php

use Avarewase\SsoClient\Http\Controllers\AvarewaseAuthController;
use Illuminate\Support\Facades\Route;

$config = config('avarewase-sso.routes');

Route::middleware($config['middleware'])
    ->prefix($config['prefix'])
    ->group(function () use ($config) {
        Route::get('/', [AvarewaseAuthController::class, 'redirect'])->name($config['login_name']);
        Route::get('/callback', [AvarewaseAuthController::class, 'callback'])->name($config['callback_name']);
    });
