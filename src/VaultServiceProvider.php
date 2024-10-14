<?php

namespace LaravelVault;

use LaravelVault\Commands\VaultStorageCommand;
use LaravelVault\Commands\VaultUnsealCommand;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use LaravelVault\Enums\VaultAuthType;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('vault', function ($app) {
            extract(config('vault'));

            if (empty($url)) {
                $url = "$schema://$host:$port";
            }

            $client = new VaultClient(
                address: $url ?? '',
                storage: $storage ?? '',
                prefix: $key_prefix ?? '',
                token: $token ?? '');

            if (!empty($policy_template)) {
                $client->setPolicyTemplate($policy_template);
            }

            if ($timeout ?? null) {
                $client->setTimeout($timeout);
            }

            if ($retries ?? null) {
                $client->setRetries($retries);
            }


            $authConfig = config("vault.auth.$auth_type", []);

            if ($authConfig) {
                $authType = VaultAuthType::from($authConfig['type']);
                $client->setAuth($authType, $authConfig);
            }

            return $client;
        });

        $this->commands([
            VaultStorageCommand::class,
            VaultUnsealCommand::class,
        ]);
    }


    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/vault.php' => config_path('vault.php'),
        ], 'config');

    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['vault'];
    }
}
