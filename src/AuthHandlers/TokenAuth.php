<?php

namespace LaravelVault\AuthHandlers;

use Illuminate\Support\Facades\Log;
use LaravelVault\Vault;

class TokenAuth implements AuthContract
{
    private string $token;

    public function __construct(Vault $client, private array $config)
    {
        $this->token = $config['token'] ?? '';

        if (!$this->token && $config['token_file']) {
            if (\file_exists($config['token_file'])) {
                $this->token = trim(\file_get_contents($config['token_file']), " \n");

            } else {
                Log::warning("Vault Token File '{$this->config['token_file']}' not Fount ");
            }
        }

        if (!$this->token && $config['token_from_unseal_file']) {
            if (\file_exists($config['token_from_unseal_file'])) {
                $unsealKeys = json_decode(\file_get_contents($config['token_from_unseal_file']), true);
                $this->token = $unsealKeys['root_token'] ?? '';
            } else {
                Log::warning("Vault Unseal Key File '{$this->config['token_from_unseal_file']}' not Found");
            }
        }


        if (!$this->token) {
            Log::warning('Failed to retrieve Vault Token');
        }
    }


    public function getToken(): string
    {
        return $this->token;
    }
}
