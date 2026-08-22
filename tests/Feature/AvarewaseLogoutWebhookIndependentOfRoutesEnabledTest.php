<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Tests\Fixtures\GuardedTestUser;
use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces AR-Eftkad's setup: AVAREWASE_SSO_ROUTES_ENABLED=false because it
 * uses its own login/callback controllers, not this package's
 * AvarewaseAuthController. The logout webhook receiver must still load —
 * it's registered independently of that flag in the service provider.
 */
class AvarewaseLogoutWebhookIndependentOfRoutesEnabledTest extends TestCase
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

        $app['config']->set('avarewase-sso.logout_webhook.secret', 'test-logout-secret');
        $app['config']->set('avarewase-sso.user_model', GuardedTestUser::class);
        $app['config']->set('avarewase-sso.routes.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avarewase_sub')->nullable()->unique();
            $table->string('remember_token')->nullable();
        });
    }

    public function test_the_webhook_route_still_responds_when_routes_enabled_is_false(): void
    {
        GuardedTestUser::query()->forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avarewase_sub' => 'sub-123',
        ]);

        $payload = [
            'event' => 'user.access_revoked',
            'client_id' => 'test-client-id',
            'sub' => 'sub-123',
            'revoked_at' => '2026-08-22T00:00:00+00:00',
            'nonce' => 'abc123',
        ];
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'test-logout-secret');

        $response = $this->call(
            'POST',
            '/login/avarewase/logout-webhook',
            [],
            [],
            [],
            ['HTTP_X-SSO-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );

        $response->assertStatus(204);
    }
}
