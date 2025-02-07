<?php

namespace LaravelVault\AuthHandlers;

use LaravelVault\VaultClient;
use Illuminate\Support\Facades\Log;

class TokenAuth implements AuthContract
{
    private string $token;

    public function __construct(private VaultClient $client, private array $config)
    {
        $this->token = $config['token'] ?? '';

        if ($this->token) {
            return;
        }

        if (!$config['token_file']) {
            Log::error(new \Exception("Can't resolve Vault Token"));

            return;
        }

        if (!\file_exists($config['token_file'])) {
            Log::error(new \Exception("Specified Vault Token File doesn't exists"));
        }

        $this->token = trim(\file_get_contents($config['token_file']), " \n");
    }


    public function getToken(): string
    {
        return $this->token;
    }
}
