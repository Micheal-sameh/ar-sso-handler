<?php

namespace Avarewase\SsoClient\Client;

use Avarewase\SsoClient\DataObjects\AvarewaseTokens;
use Avarewase\SsoClient\DataObjects\AvarewaseUserInfo;
use Avarewase\SsoClient\Exceptions\AvarewaseConnectionException;
use Avarewase\SsoClient\Exceptions\AvarewaseTokenException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AvarewaseClient
{
    public function __construct(
        protected array $config,
        protected PkceGenerator $pkce,
    ) {
    }

    /**
     * Build the `/oauth/authorize` URL for the given PKCE challenge + state.
     */
    public function authorizationUrl(string $state, string $codeChallenge): string
    {
        $query = http_build_query([
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scopes']),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return rtrim($this->config['base_url'], '/').$this->config['authorize_path'].'?'.$query;
    }

    public function pkce(): PkceGenerator
    {
        return $this->pkce;
    }

    /**
     * Exchange an authorization code for an access/refresh token pair.
     *
     * @throws AvarewaseConnectionException if the server is unreachable after retries
     * @throws AvarewaseTokenException if the server rejects the exchange
     */
    public function exchangeCodeForTokens(string $code, string $codeVerifier): AvarewaseTokens
    {
        $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post($this->url($this->config['token_path']), [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]), 'Token exchange failed');

        return AvarewaseTokens::fromResponse($this->jsonOrFail($response, 'Token exchange failed'));
    }

    /**
     * @throws AvarewaseConnectionException
     * @throws AvarewaseTokenException
     */
    public function refreshTokens(string $refreshToken): AvarewaseTokens
    {
        $response = $this->send(fn (PendingRequest $http) => $http->asForm()->post($this->url($this->config['token_path']), [
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
        ]), 'Token refresh failed');

        return AvarewaseTokens::fromResponse($this->jsonOrFail($response, 'Token refresh failed'));
    }

    /**
     * @throws AvarewaseConnectionException
     * @throws AvarewaseTokenException
     */
    public function userInfo(string $accessToken): AvarewaseUserInfo
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withToken($accessToken)
            ->get($this->url($this->config['userinfo_path'])), 'Fetching userinfo failed');

        $body = $this->jsonOrFail($response, 'Fetching userinfo failed');

        return AvarewaseUserInfo::fromResponse($body['data'] ?? $body);
    }

    protected function request(): PendingRequest
    {
        $http = $this->config['http'];

        return Http::timeout($http['timeout'])
            ->connectTimeout($http['connect_timeout'])
            ->retry($http['retry_times'], $http['retry_sleep_ms'], throw: false)
            ->acceptJson();
    }

    /**
     * Run an HTTP call, translating network-level failures (timeouts, DNS,
     * refused connections — even after Laravel's built-in retries are
     * exhausted) into AvarewaseConnectionException.
     */
    protected function send(\Closure $call, string $context)
    {
        try {
            return $call($this->request());
        } catch (ConnectionException $e) {
            throw new AvarewaseConnectionException("{$context}: Avarewase SSO server unreachable — {$e->getMessage()}", previous: $e);
        }
    }

    protected function jsonOrFail($response, string $context): array
    {
        if ($response->serverError()) {
            throw new AvarewaseConnectionException("{$context}: Avarewase SSO server error (status {$response->status()}).");
        }

        if ($response->failed()) {
            $message = $response->json('message') ?? $response->json('error_description') ?? $response->body();

            throw new AvarewaseTokenException("{$context}: {$message}");
        }

        return $response->json();
    }

    protected function url(string $path): string
    {
        return rtrim($this->config['base_url'], '/').$path;
    }
}
