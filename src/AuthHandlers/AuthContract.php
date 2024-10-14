<?php

namespace LaravelVault\AuthHandlers;

use LaravelVault\VaultClient;

interface AuthContract
{
    public function __construct(VaultClient $client, array $config);

    public function getToken(): string;
}
