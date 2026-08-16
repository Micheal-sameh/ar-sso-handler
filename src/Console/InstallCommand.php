<?php

namespace Avarewase\SsoClient\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'avarewase-sso:install {--force : Overwrite already-published files}';

    protected $description = 'Install the Avarewase SSO client: publish config/migration/views, add .env variables, run migrations';

    public function handle(): int
    {
        $this->components->info('Installing Avarewase SSO client...');

        $this->publishTag('avarewase-sso-config', 'config/avarewase-sso.php');
        $this->publishTag('avarewase-sso-migrations', 'the users-table migration');
        $this->publishTag('avarewase-sso-views', 'the login-button view');

        $this->mergeEnvFile(base_path('.env'));
        $this->mergeEnvFile(base_path('.env.example'), silentIfMissing: true);

        if ($this->confirm('Run "php artisan migrate" now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('Avarewase SSO client installed.');
        $this->components->task('Register this app as an OAuth client at https://auth.avarewase.com/admin/clients');
        $this->components->task('Set AVAREWASE_SSO_CLIENT_ID / AVAREWASE_SSO_CLIENT_SECRET / AVAREWASE_SSO_REDIRECT_URI in .env');
        $this->components->task('Add <x-avarewase-sso::login-button /> to your login page');

        return self::SUCCESS;
    }

    protected function publishTag(string $tag, string $label): void
    {
        $this->components->task("Publishing {$label}", function () use ($tag) {
            $this->callSilently('vendor:publish', array_filter([
                '--tag' => $tag,
                '--force' => $this->option('force'),
            ]));

            return true;
        });
    }

    protected function mergeEnvFile(string $path, bool $silentIfMissing = false): void
    {
        if (! File::exists($path)) {
            if (! $silentIfMissing) {
                $this->components->warn("Skipped {$path} (file does not exist)");
            }

            return;
        }

        $contents = File::get($path);

        if (str_contains($contents, 'AVAREWASE_SSO_CLIENT_ID')) {
            $this->components->twoColumnDetail(basename($path), 'already contains Avarewase SSO variables, skipped');

            return;
        }

        $stub = File::get(__DIR__.'/../../stubs/avarewase-sso.env.example');

        File::put($path, rtrim($contents)."\n\n".$stub);

        $this->components->twoColumnDetail(basename($path), 'Avarewase SSO variables added');
    }
}
