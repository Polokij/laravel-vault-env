<?php

namespace LaravelVault\Commands;

use Illuminate\Console\Command;
use LaravelVault\Commands\Traits\HelperTrait;

class VaultStatus extends Command
{
    use HelperTrait;

    protected $signature = 'vault:status';

    protected $description = 'Show Vault status';

    public function handle(): void
    {
        $this->getUnsealKeys();

        $this->fetchStatus(true);
    }
}
