<?php

namespace LaravelVault\Commands;

use Illuminate\Console\Command;
use LaravelVault\VaultFacade as Vault;

class VaultStorage extends Command
{
    protected $signature = 'vault:storage {action} {name}';

    protected $description = 'Create a vault storage';


    public function handle(): void
    {
        switch ($this->argument('action')) {
            case 'create':
                $storageConfig = config('vault.DEFAULT_STORAGE');
                $name = $this->argument('name');
                $result = Vault::instance()->sys->post("/mounts/{$name}", $storageConfig);
                $this->info(Vault::getResponse()->body(). ' ');
                break;
            default:
                $this->warn("The action {$this->argument('action')} is not implemented yet");
        }
    }
}
