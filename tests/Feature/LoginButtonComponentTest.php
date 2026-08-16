<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class LoginButtonComponentTest extends TestCase
{
    public function test_login_button_renders_link_to_login_route(): void
    {
        $html = (string) $this->blade('<x-avarewase-sso::login-button />');

        $this->assertStringContainsString(route('avarewase.login'), $html);
        $this->assertStringContainsString('Login with Avarewase', $html);
    }

    public function test_login_button_accepts_custom_label_via_slot(): void
    {
        $html = (string) $this->blade('<x-avarewase-sso::login-button>Continue with Avarewase</x-avarewase-sso::login-button>');

        $this->assertStringContainsString('Continue with Avarewase', $html);
    }

    public function test_login_route_is_registered(): void
    {
        $this->assertTrue(Route::has('avarewase.login'));
        $this->assertTrue(Route::has('avarewase.callback'));
    }
}
