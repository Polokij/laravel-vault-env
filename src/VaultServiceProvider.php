<?php

namespace LaravelVault;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use LaravelVault\AuthHandlers\{KubernetesAuth, TokenAuth};
use LaravelVault\Commands\{VaultInit, VaultStatus, VaultStorage, VaultUnseal};
use LaravelVault\Enums\VaultAuthType;
use LaravelVault\Facades\Vault as VaultFacade;

/**
 * Class VaultServiceProvider
 *
 * @package LaravelVault
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 */
class VaultServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('vault', fn($app) => $this->loadVaultInstance());

        $this->commands([
            VaultStorage::class,
            VaultUnseal::class,
            VaultInit::class,
            VaultStatus::class,
        ]);

        AliasLoader::getInstance()->alias('Vault', VaultFacade::class);
    }


    /**
     * @return void
     */
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


    /**
     * @return Vault
     * @throws Exceptions\KubernetesJWTInvalid
     * @throws Exceptions\KubernetesJWTNotFound
     */
    protected function loadVaultInstance(): Vault
    {
        $config = config('vault');
        extract($config);

        if (empty($url)) {
            $url = "$schema://$host:$port";
        }

        $client = new Vault(
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
            /** @var TokenAuth|KubernetesAuth $authType */
            $authType = VaultAuthType::from($authConfig['type']);

            if ($authType === VaultAuthType::TOKEN && $authConfig['token_from_unseal_file'] === true) {
                // set the unseal key files from the top level of config
                $authConfig['token_from_unseal_file'] = $config['unseal_keys_file'];
            }

            $client->setAuth($authType, $authConfig)->getToken();
        }

        return $client;
    }
}
