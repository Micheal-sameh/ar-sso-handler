<?php

namespace Avarewase\SsoClient\Http\Middleware;

use Avarewase\SsoClient\Client\AvarewaseClient;
use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Avarewase\SsoClient\Exceptions\AvarewaseSsoException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resource-server guard for apps that are OAuth2 *clients* of avarewase-sso
 * rather than the OAuth server: validates the request's Bearer token via
 * AvarewaseClient::userInfo(), short-caching the result by token hash since
 * that's otherwise a network round trip on every request, resolves the local
 * user through the app's bound ProvisionsAvarewaseUsers, and sets it as the
 * authenticated user for the rest of the request.
 */
class AuthenticateWithSso
{
    public function __construct(
        protected AvarewaseClient $client,
        protected ProvisionsAvarewaseUsers $provisioner,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $cacheKey = 'avarewase-sso:userinfo:'.hash('sha256', $token);

        $raw = Cache::remember($cacheKey, config('avarewase-sso.cache_ttl', 60), function () use ($token) {
            try {
                return $this->client->userInfo($token)->raw;
            } catch (AvarewaseSsoException) {
                return null;
            }
        });

        if ($raw === null) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = $this->provisioner->resolve(AvarewaseUserInfo::fromResponse($raw));

        Auth::guard(config('avarewase-sso.guard'))->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
