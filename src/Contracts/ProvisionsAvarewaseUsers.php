<?php

namespace Avarewase\SsoClient\Contracts;

use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Illuminate\Contracts\Auth\Authenticatable;

interface ProvisionsAvarewaseUsers
{
    /**
     * Resolve (find or create) the local Authenticatable for the given
     * Avarewase userinfo payload.
     */
    public function resolve(AvarewaseUserInfo $userInfo): Authenticatable;
}
