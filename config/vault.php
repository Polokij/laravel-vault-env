<?php

return [
    // Vault address
    'url' => env('VAULT_ADDR', 'http://vault:8200'),

    // Vault parameters - required is not specified the VAULT_ADDRESS
    'host' => env('VAULT_HOST', 'vault'),
    'port' => env('VAULT_PORT', '8200'),
    'scheme' => env('VAULT_SCHEME', 'https'),

    'token' => env('VAULT_TOKEN', ''),

    // default vault engine path
    'storage' => env('VAULT_DEFAULT_ENGINE', 'secrets'),
    // the prefix will be added to each secret key on put and get requests
    'key_prefix' => trim(env('VAULT_KEY_PREFIX', ''), '/ '),

    // Default Storage parameters for creation
    'default_storage_config' => [
        "type" => "kv",
        "description" => "",
        "config" => [
            "options" => null,
            "default_lease_ttl" => "0s",
            "max_lease_ttl" => "0s",
            "force_no_cache" => false,
        ],
        "local" => false,
        "seal_wrap" => false,
        "external_entropy_access" => false,
        "options" => [
            "version" => "2",
        ],
    ],

    //Policy example:
    //
    //# Allow a token to manage its own cubbyhole
    // path "cubbyhole/*" {
    //     capabilities = ["create", "read", "update", "delete", "list"]
    // }
    'policy_template' =>   "path #PATH {
        capabilities = [#ACTIONS]
    }",

    'default_policies' => ["default"],
];
