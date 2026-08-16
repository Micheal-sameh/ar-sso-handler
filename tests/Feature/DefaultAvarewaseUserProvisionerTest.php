<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Auth\DefaultAvarewaseUserProvisioner;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Avarewase\SsoClient\Tests\Fixtures\GuardedTestUser;
use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class DefaultAvarewaseUserProvisionerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avarewase_sub')->nullable()->unique();
            $table->string('avarewase_avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    protected function userInfo(string $sub = 'sub-123', string $email = 'jane@example.com'): AvarewaseUserInfo
    {
        return new AvarewaseUserInfo(
            sub: $sub,
            name: 'Jane Doe',
            email: $email,
            emailVerified: true,
            picture: 'https://auth.avarewase.test/avatar.jpg',
        );
    }

    public function test_it_saves_avarewase_sub_on_a_newly_created_user_even_when_the_model_guards_it(): void
    {
        $provisioner = new DefaultAvarewaseUserProvisioner(GuardedTestUser::class);

        $user = $provisioner->resolve($this->userInfo());

        $this->assertSame('sub-123', $user->avarewase_sub);
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com', 'avarewase_sub' => 'sub-123']);
    }

    public function test_repeat_login_finds_the_same_user_by_sub_instead_of_creating_a_duplicate(): void
    {
        $provisioner = new DefaultAvarewaseUserProvisioner(GuardedTestUser::class);

        $first = $provisioner->resolve($this->userInfo());
        $second = $provisioner->resolve($this->userInfo());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, GuardedTestUser::query()->count());
    }
}
