<?php

namespace Avarewase\SsoClient\Auth;

use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\MediaLibrary\HasMedia;
use Throwable;

/**
 * Finds a local user by `avarewase_sub`, falling back to `email`
 * (and backfilling avarewase_sub), or creates one. Expects the user
 * model to have nullable `avarewase_sub`, `avarewase_avatar`, and
 * `avarewase_membership_code` columns — see the publishable migration stubs.
 *
 * If the user model implements Spatie's `HasMedia` (spatie/laravel-medialibrary
 * is optional and not a package dependency), the picture is additionally
 * downloaded into the model's `avatar` media collection instead of only
 * storing the remote URL in `avarewase_avatar`.
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

        // Only redownload when the picture actually changed, so a returning
        // user doesn't refetch the same avatar on every login.
        $pictureChanged = $userInfo->picture
            && (! $user || $user->avarewase_avatar !== $userInfo->picture);

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
        } else {
            $user = $model->newQuery()->forceCreate($attributes);
        }

        if ($pictureChanged && $user instanceof HasMedia) {
            $this->syncAvatarMedia($user, $userInfo->picture);
        }

        return $user;
    }

    private function syncAvatarMedia(HasMedia $user, string $pictureUrl): void
    {
        try {
            $user->addMediaFromUrl($pictureUrl)->toMediaCollection('avatar');
        } catch (Throwable) {
            // Network hiccups or an unreachable/invalid picture URL shouldn't
            // block login — the URL is still saved on avarewase_avatar above.
        }
    }
}
