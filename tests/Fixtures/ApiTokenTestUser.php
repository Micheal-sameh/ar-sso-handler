<?php

namespace Avarewase\SsoClient\Tests\Fixtures;

/**
 * Stands in for a Sanctum HasApiTokens user without pulling in the whole
 * laravel/sanctum package as a test dependency — only the `tokens()` method
 * shape the webhook controller actually calls (method_exists + ->delete()).
 */
class ApiTokenTestUser extends GuardedTestUser
{
    // Static, not per-instance: the webhook controller loads its own fresh
    // model instance from the database, so an instance property set on
    // that copy would never be visible to the instance the test holds.
    public static bool $tokensDeleted = false;

    public function tokens(): object
    {
        return new class
        {
            public function delete(): void
            {
                ApiTokenTestUser::$tokensDeleted = true;
            }
        };
    }
}
