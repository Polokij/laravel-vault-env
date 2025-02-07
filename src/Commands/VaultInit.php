<?php

namespace LaravelVault\Commands;

use LaravelVault\VaultFacade as Vault;

/**
 * Class VaultInit
 * Vault API documentation: https://developer.hashicorp.com/vault/api-docs/system/init
 *
 * @package LaravelVault\Commands
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 */
class VaultInit extends VaultUnseal
{
    /**
     * @var string
     */
    protected $signature = 'vault:init 
        { --root-token : Display root token } 
        { --show-keys : Write the unseal keys to stdout } 
        { --unseal : Unseal the Vault after init }';

    /**
     * @var string
     */
    protected $description = 'Init Vault Hashicorp.';

    /**
     * @return void
     */
    public function handle(): void
    {
        $this->fetchStatus(true);

        if ($this->status['initialized']) {
            $this->info('Vault Already Initialized');
        } else {
            $this->initVault();
        }

        if ($this->option('--root-token')) {
            $this->info('Root Token: '. $this->getUnsealKeys()['root_token']);
        }

        if ($this->option('--unseal')) {
            if (!$this->status['sealed']) {
                $this->info('Vault Already Unsealed');
                exit(0);
            }

            collect($this->getUnsealKeys()['keys'])->each(fn($key) => $this->unseal($key));
            $this->fetchStatus(true);
        }
    }

    /**
     * @return void
     */
    private function intitVault(): void
    {
        $unsealKeys = Vault::init();

        if ($this->option('show-keys')) {
            $tableData = collect($unsealKeys['keys'])
                ->map(fn($key, $index) => [$key, $unsealKeys['keys_base64'][$index]]);

            $this->info('Vault Successfully Initialized');
            $this->table(['Unseal keys', 'Unseal Keys Base64'], $tableData->toArray());
            $this->info('Root Token: '.$unsealKeys['root_token']);
        }

        if ($file = config('vault.unseal_keys_file')) {
            $pathInfo = \pathinfo($file);
            if (!\file_exists($pathInfo['dirname'])) {
                \mkdir($pathInfo['dirname']);
            }

            \file_put_contents($file, \json_encode($unsealKeys, \JSON_PRETTY_PRINT));

            $this->info('Unseal keys stored on: '.$file);
        }

        $this->unsealKeys = $unsealKeys;
    }
}
