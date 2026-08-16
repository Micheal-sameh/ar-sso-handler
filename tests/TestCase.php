<?php

namespace Avarewase\SsoClient\Tests;

use Avarewase\SsoClient\Providers\AvarewaseSsoServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AvarewaseSsoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('avarewase-sso.client_id', 'test-client-id');
        $app['config']->set('avarewase-sso.client_secret', 'test-client-secret');
        $app['config']->set('avarewase-sso.redirect_uri', 'https://app.test/login/avarewase/callback');
        $app['config']->set('avarewase-sso.base_url', 'https://auth.avarewase.test');
        $app['config']->set('database.default', 'testing');
    }
}
