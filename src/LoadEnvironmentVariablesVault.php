<?php

namespace LaravelVault;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\{Env, Facades\Facade, Facades\Http};
use Illuminate\Http\Client\RequestException;

/**
 * Class LoadEnvironmentVariablesVault
 *
 * @author  Vitalii Liubimov <vitalii@liubimov.org>
 * @package LaravelVault
 */
class LoadEnvironmentVariablesVault
{
    /**
     * Bootstrap the given application.
     *
     * @param  Application  $app
     *
     * @return void
     * @throws RequestException
     */
    public function bootstrap(Application $app)
    {
        $vault = $this->getVaultClient();
        $this->bootstrapRequiredServices($app);

        $envVariables = collect($vault->secret());

        if (!$vault->getResponse()->successful()) {
            return;
        }

        // Overriding the values from .env file
        $envRepository = Env::getRepository();
        $envVariables->each(fn($value, $key) => $envRepository->set($key, (string) $value));
    }


    /**
     * @return VaultClient
     */
    protected function getVaultClient(): VaultClient
    {
        $vaultClient = new VaultClient(
            address: env('VAULT_ADDR'),
            storage: env('VAULT_STORAGE'),
            prefix: env('VAULT_KEY_PREFIX'),
            token: env('VAULT_TOKEN')
        );
        $vaultClient->setThrowException(false);

        return $vaultClient;
    }


    /**
     * @param $app
     *
     * @return void
     */
    public function bootstrapRequiredServices($app)
    {
        $items = [];
        $app->instance('config', $config = new Repository($items));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        AliasLoader::getInstance(['Http' => Http::class,]);
    }
}
