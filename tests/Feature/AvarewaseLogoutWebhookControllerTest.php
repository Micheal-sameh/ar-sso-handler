<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Tests\Fixtures\ApiTokenTestUser;
use Avarewase\SsoClient\Tests\Fixtures\GuardedTestUser;
use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class AvarewaseLogoutWebhookControllerTest extends TestCase
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
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('avarewase_sub')->nullable()->unique();
            $table->string('remember_token')->nullable();
        });
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'event' => 'user.access_revoked',
            'client_id' => 'test-client-id',
            'sub' => 'sub-123',
            'revoked_at' => now()->toIso8601String(),
            'nonce' => \Illuminate\Support\Str::random(16),
        ], $overrides);
    }

    protected function signedPost(array $payload, string $secret = 'test-logout-secret'): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

        return $this->call(
            'POST',
            '/login/avarewase/logout-webhook',
            [],
            [],
            [],
            ['HTTP_X-SSO-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    public function test_it_rotates_the_remember_token_for_a_matching_user_on_a_validly_signed_request(): void
    {
        $user = GuardedTestUser::query()->forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avarewase_sub' => 'sub-123',
            'remember_token' => 'old-token',
        ]);

        $response = $this->signedPost($this->payload());

        $response->assertStatus(204);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_it_revokes_sanctum_style_api_tokens_for_a_matching_user(): void
    {
        config(['avarewase-sso.user_model' => ApiTokenTestUser::class]);
        ApiTokenTestUser::$tokensDeleted = false;

        ApiTokenTestUser::query()->forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avarewase_sub' => 'sub-123',
        ]);

        $response = $this->signedPost($this->payload());

        $response->assertStatus(204);
        $this->assertTrue(ApiTokenTestUser::$tokensDeleted);
    }

    public function test_it_rejects_a_request_with_an_invalid_signature(): void
    {
        $response = $this->signedPost($this->payload(), secret: 'wrong-secret');

        $response->assertStatus(401);
    }

    public function test_it_rejects_a_payload_for_a_different_client_id(): void
    {
        $response = $this->signedPost($this->payload(['client_id' => 'some-other-client']));

        $response->assertStatus(422);
    }

    public function test_it_returns_204_for_an_unknown_sub_without_erroring(): void
    {
        $response = $this->signedPost($this->payload(['sub' => 'sub-does-not-exist']));

        $response->assertStatus(204);
    }

    public function test_it_returns_501_when_no_logout_secret_is_configured(): void
    {
        config(['avarewase-sso.logout_webhook.secret' => null]);

        $response = $this->signedPost($this->payload());

        $response->assertStatus(501);
    }

    public function test_it_rejects_a_request_with_a_stale_revoked_at_timestamp(): void
    {
        $response = $this->signedPost($this->payload(['revoked_at' => now()->subMinutes(10)->toIso8601String()]));

        $response->assertStatus(409);
    }

    public function test_it_rejects_the_same_nonce_replayed_a_second_time(): void
    {
        GuardedTestUser::query()->forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avarewase_sub' => 'sub-123',
        ]);

        $payload = $this->payload();

        $first = $this->signedPost($payload);
        $second = $this->signedPost($payload);

        $first->assertStatus(204);
        $second->assertStatus(409);
    }

    public function test_a_user_updated_event_refreshes_the_local_profile_without_logging_the_user_out(): void
    {
        $user = GuardedTestUser::query()->forceCreate([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'avarewase_sub' => 'sub-123',
            'remember_token' => 'unchanged-token',
        ]);

        $response = $this->signedPost($this->payload([
            'event' => 'user.updated',
            'updated_at' => now()->toIso8601String(),
            'revoked_at' => null,
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'email_verified' => true,
        ]));

        $response->assertStatus(204);

        $fresh = $user->fresh();
        $this->assertSame('Jane Doe', $fresh->name);
        $this->assertSame('jane.doe@example.com', $fresh->email);
        $this->assertSame('unchanged-token', $fresh->remember_token);
    }

    public function test_it_rejects_a_user_updated_event_with_a_stale_updated_at_timestamp(): void
    {
        $response = $this->signedPost($this->payload([
            'event' => 'user.updated',
            'updated_at' => now()->subMinutes(10)->toIso8601String(),
            'revoked_at' => null,
        ]));

        $response->assertStatus(409);
    }
}
