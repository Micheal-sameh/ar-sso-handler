<?php

namespace Avarewase\SsoClient\Tests\Feature;

use Avarewase\SsoClient\Tests\TestCase;
use Illuminate\Support\Facades\File;

class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(base_path('.env'));
        File::delete(config_path('avarewase-sso.php'));
        File::deleteDirectory(resource_path('views/auth'));

        parent::tearDown();
    }

    public function test_it_publishes_config_and_appends_env_vars(): void
    {
        File::put(base_path('.env'), "APP_NAME=Testbench\nAPP_ENV=testing\n");

        $this->artisan('avarewase-sso:install')
            ->expectsConfirmation('Run "php artisan migrate" now?', 'no')
            ->assertExitCode(0);

        $this->assertFileExists(config_path('avarewase-sso.php'));

        $env = File::get(base_path('.env'));
        $this->assertStringContainsString('APP_NAME=Testbench', $env);
        $this->assertStringContainsString('AVAREWASE_SSO_CLIENT_ID', $env);
    }

    public function test_it_skips_env_file_that_already_has_the_variables(): void
    {
        File::put(base_path('.env'), "APP_NAME=Testbench\nAVAREWASE_SSO_CLIENT_ID=already-here\n");

        $this->artisan('avarewase-sso:install')
            ->expectsConfirmation('Run "php artisan migrate" now?', 'no')
            ->assertExitCode(0);

        $env = File::get(base_path('.env'));
        $this->assertSame(1, substr_count($env, 'AVAREWASE_SSO_CLIENT_ID'));
    }

    public function test_it_inserts_login_button_into_detected_login_view(): void
    {
        File::put(base_path('.env'), "APP_NAME=Testbench\n");
        File::ensureDirectoryExists(resource_path('views/auth'));
        File::put(resource_path('views/auth/login.blade.php'), <<<'BLADE'
            @section('content')
                <form method="POST" action="/login">
                    <button type="submit">Sign in</button>
                </form>
            @endsection
            BLADE);

        $this->artisan('avarewase-sso:install')
            ->expectsConfirmation('Run "php artisan migrate" now?', 'no')
            ->assertExitCode(0);

        $view = File::get(resource_path('views/auth/login.blade.php'));
        $this->assertStringContainsString('x-avarewase-sso::login-button', $view);
    }

    public function test_it_does_not_duplicate_the_login_button_on_reinstall(): void
    {
        File::put(base_path('.env'), "APP_NAME=Testbench\n");
        File::ensureDirectoryExists(resource_path('views/auth'));
        File::put(resource_path('views/auth/login.blade.php'), <<<'BLADE'
            @section('content')
                <form method="POST" action="/login">
                    <button type="submit">Sign in</button>
                </form>
            @endsection
            BLADE);

        $this->artisan('avarewase-sso:install')->expectsConfirmation('Run "php artisan migrate" now?', 'no')->assertExitCode(0);
        $this->artisan('avarewase-sso:install --force')->expectsConfirmation('Run "php artisan migrate" now?', 'no')->assertExitCode(0);

        $view = File::get(resource_path('views/auth/login.blade.php'));
        $this->assertSame(1, substr_count($view, 'x-avarewase-sso::login-button'));
    }
}
