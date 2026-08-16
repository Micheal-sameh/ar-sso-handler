<?php

namespace Avarewase\SsoClient\Tests\Unit;

use Avarewase\SsoClient\Client\PkceGenerator;
use Avarewase\SsoClient\Tests\TestCase;

class PkceGeneratorTest extends TestCase
{
    public function test_verifier_and_challenge_are_url_safe_and_deterministic(): void
    {
        $pkce = new PkceGenerator;

        $verifier = $pkce->verifier();
        $challenge = $pkce->challengeFor($verifier);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $verifier);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $challenge);
        $this->assertSame($challenge, $pkce->challengeFor($verifier));
    }

    public function test_state_is_random_and_unique(): void
    {
        $pkce = new PkceGenerator;

        $this->assertNotSame($pkce->state(), $pkce->state());
    }
}
