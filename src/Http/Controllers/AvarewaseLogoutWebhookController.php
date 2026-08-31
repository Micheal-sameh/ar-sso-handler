<?php

namespace Avarewase\SsoClient\Http\Controllers;

use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives the SSO's back-channel webhook (see NotifyClientLogoutJob and
 * NotifyClientUserUpdatedJob in the avarewase-sso app), both posted to the
 * same client-configured URL and distinguished by the `event` field:
 *
 * - `user.access_revoked`: invalidates whatever local credential this app
 *   issued at login time — a session (apps using AvarewaseAuthController)
 *   and/or Sanctum API tokens (apps with their own controller calling
 *   $user->createToken(), e.g. AR-Eftkad). Without this, revoking a user's
 *   access/token on the SSO has no effect on an already-authenticated
 *   session or a previously issued API token.
 * - `user.updated`: refreshes the local user's cached profile fields when an
 *   admin edits them on the SSO, instead of waiting for the user's next
 *   login. The payload carries the same OIDC-shaped fields as /api/userinfo
 *   (see AvarewaseUserInfo), since this app has no stored access token to
 *   fetch them with itself.
 */
class AvarewaseLogoutWebhookController extends Controller
{
    /**
     * Window a request's timestamp (`revoked_at` / `updated_at`) must fall
     * within, and how long its `nonce` is remembered to reject replays — see
     * isReplay().
     */
    protected const REPLAY_WINDOW_SECONDS = 300;

    public function __construct(protected ProvisionsAvarewaseUsers $provisioner)
    {
    }

    public function handle(Request $request): Response
    {
        $secret = config('avarewase-sso.logout_webhook.secret');

        if (! $secret) {
            Log::warning('Avarewase SSO webhook received but AVAREWASE_SSO_LOGOUT_SECRET is not configured — ignoring.');

            return response('', 501);
        }

        if (! $this->hasValidSignature($request, $secret)) {
            Log::warning('Avarewase SSO webhook: invalid signature.');

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

        $timestamp = $payload['revoked_at'] ?? $payload['updated_at'] ?? null;

        if ($this->isReplay($timestamp, $payload['nonce'] ?? null)) {
            Log::warning('Avarewase SSO webhook: stale or replayed request rejected.', ['sub' => $sub]);

            return response('', 409);
        }

        $event = $payload['event'] ?? 'user.access_revoked';

        $userModel = config('avarewase-sso.user_model');

        /** @var (\Illuminate\Database\Eloquent\Model&Authenticatable)|null $user */
        $user = $userModel::query()->where('avarewase_sub', $sub)->first();

        if ($user && $event === 'user.updated') {
            $this->provisioner->resolve(AvarewaseUserInfo::fromResponse($payload));
        } elseif ($user) {
            $this->revokeSanctumTokens($user);
            $this->invalidateLocalSession($user);
        }

        // 204 whether or not a matching local user was found: the caller
        // (the SSO) shouldn't be able to distinguish "unknown sub" from
        // "handled successfully" — and either way there's nothing left to do.
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
     * Rejects a request whose timestamp (`revoked_at` or `updated_at`,
     * depending on event) falls outside a tight window around now (a
     * captured request replayed later), and — within that window — a
     * `nonce` seen once already (the same request replayed again quickly).
     * Cache::add() is atomic, so two identical requests racing each other
     * can't both slip through.
     */
    protected function isReplay(?string $timestamp, ?string $nonce): bool
    {
        if (! is_string($timestamp) || ! is_string($nonce) || $nonce === '') {
            return true;
        }

        try {
            $age = abs(CarbonImmutable::now()->diffInSeconds(CarbonImmutable::parse($timestamp)));
        } catch (\Exception $e) {
            return true;
        }

        if ($age > self::REPLAY_WINDOW_SECONDS) {
            return true;
        }

        return ! Cache::add(
            'avarewase-sso:logout-webhook:nonce:'.$nonce,
            true,
            self::REPLAY_WINDOW_SECONDS,
        );
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
