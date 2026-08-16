<?php

namespace Avarewase\SsoClient\DataObjects;

class AvarewaseUserInfo
{
    public function __construct(
        public readonly string $sub,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $picture,
        public readonly array $raw = [],
    ) {
    }

    public static function fromResponse(array $data): self
    {
        return new self(
            sub: $data['sub'],
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            emailVerified: (bool) ($data['email_verified'] ?? false),
            picture: $data['picture'] ?? null,
            raw: $data,
        );
    }
}
