<?php

namespace LaravelVault\Commands;

use Illuminate\Console\Command;
use LaravelVault\Commands\Traits\HelperTrait;
use LaravelVault\VaultFacade as Vault;

/**
 * Class VaultUnseal
 *
 * @package LaravelVault\Commands
 * @author Vitalii Liubimov <vitalii@liubimov.org>
 */
class VaultUnseal extends Command
{
    use HelperTrait; 
    
    /**
     * @var string
     */
    protected $signature = 'vault:unseal 
        {--s|status} {--r|reset} {--seal} {--i|interactive}{--f|file} {--timeout} {key?} ';

    /**
     * @var string
     */
    protected $description = 'Unseal the Vault';
    

    /**
     * @return void
     */
    public function handle(): void
    {
        if ($this->option('timeout') && \is_numeric($this->option('timeout'))) {
            Vault::setTimeout((int) $this->option('timeout'));
        }

        $this->fetchStatus($this->option('status'));
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

        $unsealKeysFile = $arguments->get('key', \config('vault.unseal_keys_file'));

        if (!$unsealKeysFile) {
            $this->error('No keys provided for vault unseal');

            exit(1);
        }

        if ($this->option('file')) {
            $filename = $arguments->get('key', \config('vault.unseal_keys_file')) ;

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
            Vault::setTimeout(3);// last unseal request could take some time for if using S3 buckend

            $unsealKeys->each(fn($key) => $this->unseal($key));
        } else {
            collect(\explode(',', $arguments['key']))
                ->each(fn($key) => $this->unseal($key));
        }

        $this->fetchStatus(true);
    }


}
