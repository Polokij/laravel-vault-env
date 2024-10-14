<?php

namespace LaravelVault\Enums;

enum VaultAuthType: string
{
    case TOKEN = 'token';
    case KUBERNETES = 'kubernetes';
}
