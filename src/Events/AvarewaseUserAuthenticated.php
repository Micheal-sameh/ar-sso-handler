<?php

namespace Avarewase\SsoClient\Events;

use Avarewase\SsoClient\DataObjects\AvarewaseTokens;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class AvarewaseUserAuthenticated
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly AvarewaseUserInfo $userInfo,
        public readonly AvarewaseTokens $tokens,
    ) {
    }
}
