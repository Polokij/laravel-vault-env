<?php

namespace LaravelVault;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LaravelVault\Commands\VaultInit;
use LaravelVault\Commands\VaultStorage;
use LaravelVault\Commands\VaultUnseal;
use LaravelVault\Enums\VaultAuthType;

class VaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('vault', function ($app) {
            $config = config('vault');
            extract($config);

            if (empty($url)) {
                $url = "$schema://$host:$port";
            }

            $client = new VaultClient(
                address: $url ?? '',
                storage: $storage ?? '',
                prefix: $key_prefix ?? '',
                token: $token ?? ''
            );


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
            VaultStorage::class,
            VaultUnseal::class,
            VaultInit::class,
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
