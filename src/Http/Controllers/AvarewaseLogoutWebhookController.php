<?php

namespace Avarewase\SsoClient\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives the SSO's back-channel logout notification (see NotifyClientLogoutJob
 * in the avarewase-sso app) and invalidates whatever local credential this app
 * issued at login time — a session (apps using AvarewaseAuthController) and/or
 * Sanctum API tokens (apps with their own controller calling $user->createToken(),
 * e.g. AR-Eftkad). Without this, revoking a user's access/token on the SSO has
 * no effect on an already-authenticated session or a previously issued API token.
 */
class AvarewaseLogoutWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('avarewase-sso.logout_webhook.secret');

        if (! $secret) {
            Log::warning('Avarewase SSO logout webhook received but AVAREWASE_SSO_LOGOUT_SECRET is not configured — ignoring.');

            return response('', 501);
        }

        if (! $this->hasValidSignature($request, $secret)) {
            Log::warning('Avarewase SSO logout webhook: invalid signature.');

            return response('', 401);
        }

        $payload = $request->json()->all();

        if (($payload['client_id'] ?? null) !== config('avarewase-sso.client_id')) {
            return response('', 422);
        }

        $sub = $payload['sub'] ?? null;

        if (! is_string($sub) || $sub === '') {
            return response('', 422);
        }

        $userModel = config('avarewase-sso.user_model');

        /** @var (\Illuminate\Database\Eloquent\Model&Authenticatable)|null $user */
        $user = $userModel::query()->where('avarewase_sub', $sub)->first();

        if ($user) {
            $this->revokeSanctumTokens($user);
            $this->invalidateLocalSession($user);
        }

        // 204 whether or not a matching local user was found: the caller
        // (the SSO) shouldn't be able to distinguish "unknown sub" from
        // "logged out successfully" — and either way there's nothing left to do.
        return response('', 204);
    }

    /**
     * The SSO signs the exact raw JSON body it sent with HMAC-SHA256, so we
     * must verify against the raw bytes rather than a re-encoded payload —
     * key order/whitespace differences would otherwise break a valid signature.
     */
    protected function hasValidSignature(Request $request, string $secret): bool
    {
        $header = (string) $request->header('X-SSO-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    /**
     * Apps using Laravel Sanctum's HasApiTokens trait (e.g. AR-Eftkad, which
     * issues a token per login via $user->createToken() and — by its own
     * design — never revokes old ones itself) get all their tokens deleted,
     * forcing every device/session using them to re-authenticate.
     */
    protected function revokeSanctumTokens(Authenticatable $user): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }

    protected function invalidateLocalSession(Authenticatable $user): void
    {
        // Rotating the remember token stops the persistent "remember me"
        // cookie from silently re-authenticating the user on a future visit.
        if (method_exists($user, 'forceFill')) {
            $user->forceFill(['remember_token' => Str::random(60)])->save();
        }

        // If sessions are centrally stored (database driver), also drop any
        // currently-active session rows for this user so an already-open
        // browser tab is kicked out on its very next request, not just
        // blocked from a future silent re-login. File/cookie session drivers
        // have no shared store to purge from, so an open tab there will only
        // be caught once its session naturally expires.
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getAuthIdentifier())
                ->delete();
        }
    }
}
