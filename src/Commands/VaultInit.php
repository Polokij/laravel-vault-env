<?php

namespace LaravelVault\Commands;

use Illuminate\Console\Command;
use LaravelVault\Commands\Traits\HelperTrait;

/**
 * Class VaultInit
 * Vault API documentation: https://developer.hashicorp.com/vault/api-docs/system/init
 *
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 * @package LaravelVault\Commands
 */
class VaultInit extends Command
{
    use HelperTrait;
    
    /**
     * @var string
     */
    protected $signature = 'vault:init {--root-token : Display root token} {--show-keys : Write the unseal keys to stdout} {--unseal : Unseal the Vault after init}';

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

        if ($this->option('root-token')) {
            $this->info('Root Token: '.$this->getUnsealKeys()['root_token']);
        }

        if ($this->option('unseal')) {
            if (!$this->status['sealed']) {
                $this->info('Vault Already Unsealed');
                exit(0);
            }

            collect($this->getUnsealKeys()['keys'])->each(fn($key) => $this->unseal($key));
            $this->fetchStatus(true);
        }
    }



}
