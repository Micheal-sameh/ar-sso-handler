<?php

namespace Avarewase\SsoClient\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Deliberately guarded like a real-world User model (only a subset of
 * columns fillable) — reproduces the mass-assignment gap where the
 * provisioner's `create()` path silently dropped avarewase_sub/avatar.
 */
class GuardedTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    public $timestamps = false;
}
