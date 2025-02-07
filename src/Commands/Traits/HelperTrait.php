<?php

namespace LaravelVault\Commands\Traits;

use Illuminate\Support\Collection;
use LaravelVault\VaultFacade as Vault;

trait HelperTrait
{

    /**
     * @param $display
     *
     * @return void
     */
    protected function fetchStatus($display)
    {
        $this->status = collect(Vault::status());

        if ($display) {
            $this->displayResponse($this->status);
        }
    }


    /**
     * @param Collection $collection
     *
     * @return void
     */
    protected function displayResponse(Collection $collection)
    {
        $collection->each(fn($value, $key) => $this->info("$key: ".\json_encode($value),));
    }


    /**
     * @param string $key
     *
     * @return bool
     */
    protected function unseal(string $key): bool
    {
        $result = Vault::unseal($key);

        if (Vault::getResponse()->successful()) {
            $this->status = collect($result);
        } else {
            Log::error(Vault::getResponse()->body().'');

            return false;
        }

        $this->info("Unseal progress: {$result['progress']}  Status:"
            .($result['sealed'] ? 'sealed' : 'unsealed'));

        return $result['sealed'];
    }

    /**
     * @param string $file
     *
     * @return array|mixed|void
     */
    protected function getUnsealKeys(string $file = '') {
        if ($this->unsealKeys) {
            return $this->unsealKeys;
        }

        $unsealFile = $file ?: config('vault.unseal_keys_file');

        if (!$unsealFile) {
            $this->error('Unseal Keys file is not specified');

            exit(1);
        }

        $unsealKeys = json_decode(\file_get_contents($unsealFile));
        Vault::setToken($unsealKeys['root_token']);

        return $unsealKeys;
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
