<?php

namespace LaravelVault\AuthHandlers;

use LaravelVault\Vault;

interface AuthContract
{
    public function __construct(Vault $client, array $config);

    public function getToken(): string;
}
