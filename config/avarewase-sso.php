<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Avarewase SSO server
    |--------------------------------------------------------------------------
    */
    'base_url' => env('AVAREWASE_SSO_BASE_URL', 'https://auth.avarewase.com'),

    'authorize_path' => '/oauth/authorize',
    'token_path' => '/oauth/token',
    'userinfo_path' => '/api/userinfo',

    /*
    |--------------------------------------------------------------------------
    | OAuth client credentials
    |--------------------------------------------------------------------------
    | Registered via the Avarewase admin panel at /admin/clients.
    */
    'client_id' => env('AVAREWASE_SSO_CLIENT_ID'),
    'client_secret' => env('AVAREWASE_SSO_CLIENT_SECRET'),
    'redirect_uri' => env('AVAREWASE_SSO_REDIRECT_URI'),

    'scopes' => explode(' ', env('AVAREWASE_SSO_SCOPES', 'openid profile email')),

    /*
    |--------------------------------------------------------------------------
    | HTTP resilience
    |--------------------------------------------------------------------------
    | Applied to every call the package makes to the SSO server (token
    | exchange, userinfo, refresh). No local-login fallback is provided —
    | if the server is unreachable after retries, an
    | Avarewase\SsoClient\Exceptions\AvarewaseConnectionException is thrown.
    */
    'http' => [
        'timeout' => (int) env('AVAREWASE_SSO_HTTP_TIMEOUT', 5),
        'connect_timeout' => (int) env('AVAREWASE_SSO_HTTP_CONNECT_TIMEOUT', 3),
        'retry_times' => (int) env('AVAREWASE_SSO_HTTP_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('AVAREWASE_SSO_HTTP_RETRY_SLEEP_MS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local session / guard
    |--------------------------------------------------------------------------
    | The guard name a consuming app should configure in config/auth.php
    | (session driver + eloquent provider). The package logs users into
    | this guard after a successful callback — it does not ship a custom
    | Guard class, it uses Laravel's normal session guard.
    */
    'guard' => env('AVAREWASE_SSO_GUARD', 'web'),

    'user_model' => env('AVAREWASE_SSO_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | User provisioning
    |--------------------------------------------------------------------------
    | Class responsible for turning a userinfo payload into a local
    | Authenticatable. Bind your own implementation of
    | Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers in your
    | AppServiceProvider to override the default find-or-create behaviour.
    */
    'provisioner' => \Avarewase\SsoClient\Auth\DefaultAvarewaseUserProvisioner::class,

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => env('AVAREWASE_SSO_ROUTES_ENABLED', true),
        'prefix' => 'login/avarewase',
        'middleware' => ['web'],
        'login_name' => 'avarewase.login',
        'callback_name' => 'avarewase.callback',
        'redirect_after_login' => '/home',
    ],

];
