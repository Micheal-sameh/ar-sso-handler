<?php

namespace Avarewase\SsoClient\Facades;

use Avarewase\SsoClient\Client\AvarewaseClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string authorizationUrl(string $state, string $codeChallenge)
 * @method static \Avarewase\SsoClient\DataObjects\AvarewaseTokens exchangeCodeForTokens(string $code, string $codeVerifier)
 * @method static \Avarewase\SsoClient\DataObjects\AvarewaseTokens refreshTokens(string $refreshToken)
 * @method static \Avarewase\SsoClient\DataObjects\AvarewaseUserInfo userInfo(string $accessToken)
 *
 * @see AvarewaseClient
 */
class AvarewaseSso extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AvarewaseClient::class;
    }
}
