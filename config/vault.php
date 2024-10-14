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
    'auth_type' => env('VAUlt_AUTH_TYPE', 'token'),

    'auth' => [
        "token" => [
            'token' => env('VAULT_TOKEN', ''),
        ],
        "kubernetes" => [
            // kubernetes service account's token path - required for kubernetes auth
            "sa_token_path" => env('VAULT_SA_TOKEN_PATH', '/var/run/secrets/kubernetes.io/serviceaccount/token'),

            // auth endpoint for kubernetes auth type
            "auth_endpoint" => env('VAULT_AUTH_ENDPOINT', 'kubernetes'),

            // vault's kubernetes auth role for specified service's account
            "auth_role" => env('VAULT_AUTH_ROLE', 'vault'),
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
