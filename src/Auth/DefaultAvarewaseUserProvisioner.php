<?php

namespace Avarewase\SsoClient\Auth;

use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Finds a local user by `avarewase_sub`, falling back to `email`
 * (and backfilling avarewase_sub), or creates one. Expects the user
 * model to have nullable `avarewase_sub`, `avarewase_avatar`, and
 * `avarewase_membership_code` columns — see the publishable migration stubs.
 */
class DefaultAvarewaseUserProvisioner implements ProvisionsAvarewaseUsers
{
    public function __construct(protected string $userModel)
    {
    }

    public function resolve(AvarewaseUserInfo $userInfo): Authenticatable
    {
        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model = new $this->userModel;

        $user = $model->newQuery()->where('avarewase_sub', $userInfo->sub)->first();

        if (! $user && $userInfo->email) {
            $user = $model->newQuery()->where('email', $userInfo->email)->first();
        }

        $attributes = array_filter([
            'name' => $userInfo->name,
            'email' => $userInfo->email,
            'avarewase_sub' => $userInfo->sub,
            'avarewase_avatar' => $userInfo->picture,
            'date_of_birth' => $userInfo->dateOfBirth,
            'avarewase_membership_code' => $userInfo->membershipCode,
            'email_verified_at' => $userInfo->emailVerified ? now() : null,
        ], fn ($value) => ! is_null($value));

        if ($user) {
            $user->forceFill($attributes)->save();

            return $user;
        }

        return $model->newQuery()->forceCreate($attributes);
    }
}
