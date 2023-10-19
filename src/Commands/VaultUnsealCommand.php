<?php

namespace LaravelVault\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use LaravelVault\VaultFacade as Vault;

class VaultUnsealCommand extends Command
{
    protected $signature = 'vault:unseal {--s|status} {--r|reset} {--seal} {--i|interactive}{--f|file} {key?}';

    protected $description = 'Unseal the vault';

    private Collection $status;


    public function handle(): void
    {
        $this->status($this->option('status'));
        $arguments = collect($this->arguments());

        if ($this->option('seal')) {
            $this->info('Sealing the Vault');
            $seal = collect(Vault::seal());
            $this->displayResponse($seal);

            exit (Vault::getResponse()->successful() ? 0 : 2);
        }

        if (!$this->status->get('sealed')) {
            $this->warn("The vault is unsealed already");
            exit (0);
        }

        if ($this->option('reset')) {
            $reset = collect(Vault::unseal(reset: true));
            $this->displayResponse($reset);

            exit (Vault::getResponse()->successful() ? 0 : 2);
        }

        if (!$arguments->get('key', '')) {
            $this->error('No keys provided for vault unseal');

            exit(1);
        }

        if ($this->option('file')) {
            $filename = $arguments['key'];

            if (!\file_exists($filename)) {
                $this->error("File $filename not found.");
                exit (2);
            }

            $unsealData = collect(json_decode(\file_get_contents($filename), true));
            $unsealKeys = collect($unsealData->get('keys_base64', []));

            if ($unsealKeys->isEmpty()) {
                $this->error('keys_base64 not found on file');
                exit (3);
            }

            Vault::setToken($unsealData['root_token']);
            $unsealKeys->each(fn($key) => $this->unseal($key));
        } else {
            collect(\explode(',', $arguments['key']))
                ->each(fn($key) => $this->unseal($key));
        }

        $this->status(true);
        exit(0);
    }

    private function status($display) {
        $this->status = collect(Vault::status());

        if ($display) {
            $this->displayResponse($this->status);
        }
    }

    private function displayResponse(Collection $collection) {
        $collection->each(fn($value, $key) => $this->info("$key: " . \json_encode($value), ));
    }

    private function unseal(string $key): bool {
        $result = Vault::unseal($key);

        if (Vault::getResponse()->successful()) {
            $this->status = collect($result);
        } else {
            \Log::error(Vault::getResponse()->body(). '');

            return false;
        }

        $this->info("Unseal progress: {$result['progress']}  Status:"
            .  ($result['sealed'] ? 'sealed' : 'unsealed'));

        return $result['sealed'];
    }
}
