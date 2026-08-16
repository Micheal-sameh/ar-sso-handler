<?php

namespace Avarewase\SsoClient\DataObjects;

class AvarewaseTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly string $tokenType,
        public readonly int $expiresIn,
    ) {
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
            expiresIn: (int) ($data['expires_in'] ?? 0),
        );
    }
}
