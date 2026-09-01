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
    | Resource-server middleware (avarewase.auth)
    |--------------------------------------------------------------------------
    | How long (seconds) a validated Bearer token's userinfo response is
    | cached, keyed by a hash of the token. Applies to apps using the
    | Avarewase\SsoClient\Http\Middleware\AuthenticateWithSso middleware
    | instead of session login — i.e. apps acting as an OAuth2 resource
    | server rather than doing the redirect/callback flow themselves.
    */
    'cache_ttl' => (int) env('AVAREWASE_SSO_USERINFO_CACHE_TTL', 60),

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

    /*
    |--------------------------------------------------------------------------
    | Back-channel webhook (logout + profile updates)
    |--------------------------------------------------------------------------
    | The SSO calls this same route for two events, distinguished by the
    | `event` field in the body:
    |   - "user.access_revoked": an admin revoked the user's access, so this
    |     app invalidates the local session it created at login time.
    |   - "user.updated": an admin edited the user's details, so this app
    |     refreshes its locally-cached profile fields immediately instead of
    |     waiting for the user's next login.
    | Registered outside the 'web' middleware group: this is a server-to-server
    | POST with no cookies/CSRF token, authenticated instead via the signed
    | X-SSO-Signature header (HMAC-SHA256 of the raw JSON body using
    | AVAREWASE_SSO_LOGOUT_SECRET, revealed once in the SSO admin panel when
    | you set this client's "Logout Webhook URL" to this route's URL).
    */
    'logout_webhook' => [
        'enabled' => env('AVAREWASE_SSO_LOGOUT_WEBHOOK_ENABLED', true),
        'path' => env('AVAREWASE_SSO_LOGOUT_WEBHOOK_PATH', 'login/avarewase/logout-webhook'),
        'name' => 'avarewase.logout-webhook',
        'middleware' => ['throttle:30,1'],
        'secret' => env('AVAREWASE_SSO_LOGOUT_SECRET'),
    ],

];
