<?php

use Avarewase\SsoClient\Http\Controllers\AvarewaseLogoutWebhookController;
use Illuminate\Support\Facades\Route;

$webhook = config('avarewase-sso.logout_webhook');

// Deliberately not registered under the 'web' middleware group used by
// avarewase-sso.php: this is a server-to-server call authenticated by
// signature, not a browser request, and must not require a CSRF token or
// session cookie. Loaded independently of AVAREWASE_SSO_ROUTES_ENABLED too —
// an app may roll its own login/callback controllers (skipping this
// package's routes entirely) while still wanting this webhook receiver.
Route::post($webhook['path'], [AvarewaseLogoutWebhookController::class, 'handle'])
    ->middleware($webhook['middleware'])
    ->name($webhook['name']);
