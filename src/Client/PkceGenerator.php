<?php

namespace Avarewase\SsoClient\Client;

class PkceGenerator
{
    public function verifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    public function challengeFor(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function state(): string
    {
        return bin2hex(random_bytes(20));
    }
}
