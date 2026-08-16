<?php

namespace Avarewase\SsoClient\Providers;

use Avarewase\SsoClient\Client\AvarewaseClient;
use Avarewase\SsoClient\Client\PkceGenerator;
use Avarewase\SsoClient\Contracts\ProvisionsAvarewaseUsers;
use Illuminate\Support\ServiceProvider;

class AvarewaseSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/avarewase-sso.php', 'avarewase-sso');

        $this->app->singleton(PkceGenerator::class);

        $this->app->singleton(AvarewaseClient::class, function ($app) {
            return new AvarewaseClient(
                config: $app['config']->get('avarewase-sso'),
                pkce: $app->make(PkceGenerator::class),
            );
        });

        $this->app->bind(ProvisionsAvarewaseUsers::class, function ($app) {
            $provisionerClass = $app['config']->get('avarewase-sso.provisioner');

            if ($provisionerClass === \Avarewase\SsoClient\Auth\DefaultAvarewaseUserProvisioner::class) {
                return new $provisionerClass($app['config']->get('avarewase-sso.user_model'));
            }

            return $app->make($provisionerClass);
        });
    }

    public function boot(): void
    {
        if (config('avarewase-sso.routes.enabled')) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/avarewase-sso.php');
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'avarewase-sso');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/avarewase-sso.php' => config_path('avarewase-sso.php'),
            ], 'avarewase-sso-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/add_avarewase_columns_to_users_table.php.stub'
                    => database_path('migrations/'.date('Y_m_d_His', time()).'_add_avarewase_columns_to_users_table.php'),
            ], 'avarewase-sso-migrations');

            $this->publishes([
                __DIR__.'/../../resources/views' => resource_path('views/vendor/avarewase-sso'),
            ], 'avarewase-sso-views');

            $this->publishes([
                __DIR__.'/../../stubs/avarewase-sso.env.example' => base_path('.avarewase-sso.env.example'),
            ], 'avarewase-sso-env');
        }
    }
}
