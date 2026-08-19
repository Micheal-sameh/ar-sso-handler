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
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $membershipCode = null,
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
            // Accepts either key: the OIDC-standard "birthdate" claim, or
            // "date_of_birth" (the sso server's internal DTO naming, as of
            // 2026-08-16 not yet wired into its /api/userinfo response —
            // this is forward-compatible with whichever key it ships).
            dateOfBirth: $data['birthdate'] ?? $data['date_of_birth'] ?? null,
            membershipCode: $data['membership_code'] ?? null,
            raw: $data,
        );
    }
}
