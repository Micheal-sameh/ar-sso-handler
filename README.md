# Avarewase SSO Client

Laravel client SDK for **"Login with Avarewase"** — the OAuth2/OIDC + PKCE authorization server at `auth.avarewase.com`. Install this into any Laravel app that should let users log in with their Avarewase account.

Ships:

- A resilient HTTP client (`AvarewaseClient` / `AvarewaseSso` facade) for the authorize/token/userinfo endpoints, with configurable timeouts and retries — no local-login fallback, connection failures raise `AvarewaseConnectionException` so the host app decides what to do.
- Ready-made routes + controller for the full PKCE authorization-code flow.
- Pluggable user provisioning (`ProvisionsAvarewaseUsers`) with a sensible find-or-create default.
- Logs the resolved user into a normal Laravel session guard — `auth()->user()` just works, no custom Guard class.
- An `AvarewaseUserAuthenticated` event fired after every successful login.

## Installation

This package isn't on Packagist — it's hosted as a private GitHub repo. In a consuming app's `composer.json`, add it as a VCS repository:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Micheal-sameh/ar-sso-handler.git" }
]
```

Since the repo is private, Composer needs a GitHub token once per machine:

```bash
composer config --global github-oauth.github.com <a-PAT-with-repo-scope>
```

Then require a tagged release:

```bash
composer require avarewase/sso-client:^1.0
```

Working on the package itself alongside a sibling project instead? Use a path repository there for live edits:

```json
"repositories": [
    { "type": "path", "url": "../avarewase-sso-client", "options": { "symlink": true } }
]
```
```bash
composer require avarewase/sso-client:@dev
```

Then run the installer:

```bash
php artisan avarewase-sso:install
```

This publishes `config/avarewase-sso.php`, the users-table migration, and the login-button view; appends the `AVAREWASE_SSO_*` variables to `.env` (and `.env.example` if present, skipping either if the variables are already there); inserts `<x-avarewase-sso::login-button />` into `resources/views/auth/login.blade.php` or `resources/views/login.blade.php` if one exists (idempotent — safe to re-run, and it leaves the view alone if it can't find one of those paths or a login link is already there); and offers to run `php artisan migrate` for you.

Prefer to do it by hand, or just want one piece? Each publish step has its own tag:

```bash
php artisan vendor:publish --tag=avarewase-sso-config
php artisan vendor:publish --tag=avarewase-sso-migrations
php artisan vendor:publish --tag=avarewase-sso-views
php artisan vendor:publish --tag=avarewase-sso-env   # writes .avarewase-sso.env.example instead of touching .env
php artisan migrate
```

## Configuration

Set in `.env`:

```
AVAREWASE_SSO_BASE_URL=https://auth.avarewase.com
AVAREWASE_SSO_CLIENT_ID=...
AVAREWASE_SSO_CLIENT_SECRET=...
AVAREWASE_SSO_REDIRECT_URI=https://yourapp.test/login/avarewase/callback
AVAREWASE_SSO_SCOPES="openid profile email"
AVAREWASE_SSO_GUARD=web
AVAREWASE_SSO_USER_MODEL="App\Models\User"
```

Register the client (redirect URI, app name) via the Avarewase admin panel at `/admin/clients` on the auth server — see the `sso` project's own README for that flow.

## Usage

With routes enabled (default), visiting `/login/avarewase` starts the flow and `/login/avarewase/callback` completes it, logging the user into the configured guard and redirecting to `avarewase-sso.routes.redirect_after_login`.

```blade
<x-avarewase-sso::login-button />

{{-- custom label / classes --}}
<x-avarewase-sso::login-button class="w-full justify-center">
    Continue with Avarewase
</x-avarewase-sso::login-button>
```

or link to the route directly:

```blade
<a href="{{ route('avarewase.login') }}">Login with Avarewase</a>
```

Publish and edit the component's Blade view (`avarewase-sso-views` tag) if you need it styled differently from the default Tailwind classes.

### Custom user provisioning

Bind your own resolver if you need different find-or-create logic (e.g. matching on tenant + email):

```php
// AppServiceProvider::register()
$this->app->bind(
    \Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers::class,
    \App\Auth\MyAvarewaseUserProvisioner::class,
);
```

```php
class MyAvarewaseUserProvisioner implements ProvisionsAvarewaseUsers
{
    public function resolve(AvarewaseUserInfo $userInfo): Authenticatable { ... }
}
```

### Calling the SSO server directly

```php
use Avarewase\SsoClient\Facades\AvarewaseSso;

$tokens = AvarewaseSso::exchangeCodeForTokens($code, $verifier);
$userInfo = AvarewaseSso::userInfo($tokens->accessToken);
```

### Handling an unreachable SSO server

```php
use Avarewase\SsoClient\Exceptions\AvarewaseConnectionException;
use Avarewase\SsoClient\Exceptions\AvarewaseTokenException;

try {
    $tokens = AvarewaseSso::exchangeCodeForTokens($code, $verifier);
} catch (AvarewaseConnectionException $e) {
    // server unreachable after retries — show a "try again later" page
} catch (AvarewaseTokenException $e) {
    // server responded but rejected the code (expired, reused, mismatched redirect_uri, ...)
}
```

Retry behaviour is configurable via `AVAREWASE_SSO_HTTP_*` env vars (timeout, connect timeout, retry count, retry sleep).

## Testing

```bash
composer install
vendor/bin/phpunit
```
