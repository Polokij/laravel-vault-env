<?php

return [
    // Vault address
    'url' => env('VAULT_ADDR', 'http://vault:8200'),

    // Vault parameters - required is not specified the VAULT_ADDRESS
    'host' => env('VAULT_HOST', 'vault'),
    'port' => env('VAULT_PORT', '8200'),
    'scheme' => env('VAULT_SCHEME', 'https'),

    // default vault engine path
    'storage' => env('VAULT_DEFAULT_ENGINE', env('VAULT_STORAGE', 'secrets')),

    // the prefix will be added to each secret key on put and get requests
    'key_prefix' => trim(env('VAULT_KEY_PREFIX', ''), '/ '),

    //  accepted auth types: token, kubernetes
    'auth_type' => env('VAULT_AUTH', 'token'),

    // the unseal keys file's path - use to store the keys on init the vault and retrieve the keys for unseal the vault
    'unseal_keys_file' => env('VAULT_UNSEAL_KEYS_FILE', '/secrets/vault/.vault_unseal.json'),

    'auth' => [
        "token" => [
            'type' => 'token',
            'token' => env('VAULT_TOKEN', ''),
            'token_file' => env('VAULT_TOKEN_FILE', ''),
            // method is not secure. Don't use for production.
            'token_from_unseal_file' => false
        ],
        /**
         * Documentation: https://developer.hashicorp.com/vault/docs/auth/kubernetes#authentication
         */
        "kubernetes" => [
            'type' => 'kubernetes',
            // kubernetes service account's token path - required for kubernetes auth
            "sa_token_path" => env('VAULT_TOKEN_FILE', '/var/run/secrets/kubernetes.io/serviceaccount/token'),

            // auth endpoint for kubernetes auth type
            "auth_endpoint" => env('VAULT_AUTH_ENDPOINT', 'kubernetes'),

            // vault's kubernetes auth role for specified service's account
            // vault's kubernetes auth role for specified service's account
            "auth_role" => env('VAULT_AUTH_ROLE', env('VAULT_ROLE','vault')),
        ],
    ],

    // number of request's retries
    'retries' => 1,
    'timeout' => 2, // sec.

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
