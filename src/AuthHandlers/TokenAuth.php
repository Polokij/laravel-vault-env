<?php

namespace LaravelVault\AuthHandlers;

use LaravelVault\VaultClient;
use Illuminate\Support\Facades\Log;

class TokenAuth implements AuthContract
{
    private string $token;

    public function __construct(private VaultClient $client, private array $config)
    {
        $this->token = $this->config['token'] ?? '';

        if (!$this->token) {
            Log::error('Vault Token is empty');
        }
    }


    public function getToken(): string
    {
        return $this->token;
    }
}
