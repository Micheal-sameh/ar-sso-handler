<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Client\AvarewaseClient;
use Avarewase\SsoClient\Exceptions\AvarewaseConnectionException;
use Avarewase\SsoClient\Exceptions\AvarewaseTokenException;
use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AvarewaseClientTest extends TestCase
{
    public function test_authorization_url_contains_pkce_and_client_params(): void
    {
        $client = $this->app->make(AvarewaseClient::class);

        $url = $client->authorizationUrl('the-state', 'the-challenge');

        $this->assertStringStartsWith('https://auth.avarewase.test/oauth/authorize?', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=the-state', $url);
        $this->assertStringContainsString('code_challenge=the-challenge', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
    }

    public function test_exchange_code_for_tokens_returns_tokens_on_success(): void
    {
        Http::fake([
            'auth.avarewase.test/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 86400,
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-123',
            ]),
        ]);

        $client = $this->app->make(AvarewaseClient::class);
        $tokens = $client->exchangeCodeForTokens('code-abc', 'verifier-abc');

        $this->assertSame('access-123', $tokens->accessToken);
        $this->assertSame('refresh-123', $tokens->refreshToken);
    }

    public function test_exchange_code_throws_token_exception_on_invalid_grant(): void
    {
        Http::fake([
            'auth.avarewase.test/oauth/token' => Http::response(['message' => 'invalid_grant'], 400),
        ]);

        $client = $this->app->make(AvarewaseClient::class);

        $this->expectException(AvarewaseTokenException::class);

        $client->exchangeCodeForTokens('bad-code', 'verifier-abc');
    }

    public function test_exchange_code_throws_connection_exception_when_server_unreachable(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $client = $this->app->make(AvarewaseClient::class);

        $this->expectException(AvarewaseConnectionException::class);

        $client->exchangeCodeForTokens('code-abc', 'verifier-abc');
    }

    public function test_userinfo_unwraps_data_envelope(): void
    {
        Http::fake([
            'auth.avarewase.test/api/userinfo' => Http::response([
                'data' => [
                    'sub' => 'b3f1e2a0',
                    'name' => 'Jane Doe',
                    'email' => 'jane@example.com',
                    'email_verified' => true,
                    'picture' => 'https://auth.avarewase.test/storage/avatars/jane.jpg',
                ],
                'message' => null,
            ]),
        ]);

        $client = $this->app->make(AvarewaseClient::class);
        $userInfo = $client->userInfo('access-123');

        $this->assertSame('b3f1e2a0', $userInfo->sub);
        $this->assertSame('jane@example.com', $userInfo->email);
        $this->assertTrue($userInfo->emailVerified);
        $this->assertNull($userInfo->dateOfBirth);
        $this->assertNull($userInfo->membershipCode);
    }

    public function test_userinfo_reads_date_of_birth_from_either_key_name(): void
    {
        $client = $this->app->make(AvarewaseClient::class);

        Http::fake(['auth.avarewase.test/api/userinfo' => Http::response([
            'data' => ['sub' => 'a', 'birthdate' => '1990-05-20'],
        ])]);
        $this->assertSame('1990-05-20', $client->userInfo('t')->dateOfBirth);

        Http::fake(['auth.avarewase.test/api/userinfo' => Http::response([
            'data' => ['sub' => 'a', 'date_of_birth' => '1990-05-20'],
        ])]);
        $this->assertSame('1990-05-20', $client->userInfo('t')->dateOfBirth);
    }

    public function test_userinfo_reads_membership_code(): void
    {
        Http::fake([
            'auth.avarewase.test/api/userinfo' => Http::response([
                'data' => ['sub' => 'a', 'membership_code' => 'MEM-0042'],
            ]),
        ]);

        $client = $this->app->make(AvarewaseClient::class);

        $this->assertSame('MEM-0042', $client->userInfo('t')->membershipCode);
    }
}
