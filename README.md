# Laravel Hashicorp. Vault API client

This package provide the base features for Hashicorp Vault API.

### Motivation

Developing my multi-tenant application using Laravel I've faced with lacking of the package which could fetch the
env variables from vault and apply it on bootstrapping the application.
Here it bref implementation of the required features:

    - Vault API Client + Service + Facade   
    - artisan command to unseal the vault
    - artisan command for create the secret engine 
    - Laravel bootstraper which apply the env variables fetched from Vault  

## Requirements

    - Laravel 9+
    - PHP 8+

## Installation

Adding composer package

     composer require polokij/laravel-vault-env

Setting up the .env variables

    VAULT_ADDR=http://vault:8200 
    # Required is no VAULT_ADDR specified
    VAULT_HOST=vault
    VAULT_PORT=8200
    VAULT_SCHEME=https
    # The storage engine thich will be used by default kv2 or secrets, or whatewhere
    VAULT_DEFAULT_ENGINE=kv2
    # The prefix will be added to each key on put/get requests
    VAULT_KEY_PREFIX=TenantRootPath
    
    VAULT_TOKEN=

Publishing the config

    php artisan vendor:publish --provider="LaravelVault\VaultServiceProvider"

## Usage

- Unseal Vault using json ``php artisan vault:unseal -f secret/file.json``
- Unseal Vault using key as command arguments  ``php artisan vault:unseal $key``
- Check the seal status ``php artisan vault:unseal -s``
- Create new secret storage ``php artisan vault:storage create new_storage_name``
- Access to Secrets 

```php
Vault::secret('my-secret', ['foo' => 'bar']); // push the secret to Vault 
Vault::secret('my-secret'); // pull the secret from Vault - return ['foo' => 'bar']
Vault::instance()->sys->get('some/not/implemented/endpoints'); // call the other endpoints on sys group 
Vault::instance()->auth->get('some/not/implemented/endpoints'); // call the other endpoints on auth group 
```

### Bootstrap the application with dynamic env variables
Example for Multi-tenant application: 

**bootstrap/app.php**
```php
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

$app->afterLoadingEnvironment(function () use ($app) {
    // checking is the feature enabled
    if (env('VAULT_LOAD_ENV', false)) {
        try {
            $tenantId = env('TENANT_ID');

            // resolving tenant_id by headers - make sure proxy override this header for security reason
            if (!$tenantId) {
                $headers = collect(getallheaders());
                $tenantIdHeader = env('TENANT_ID_HEADER', 'tenant-id');
                $tenantId = $headers
                    ->first(fn($value, $key) => $key === $tenantIdHeader
                        || strtolower($key) === $tenantIdHeader);
            }

            if (!$tenantId) {
                throw new Exception('Missed Tenant_id ');
            }

            $envRepository = Env::getRepository();
            $vaultDefaultPrefix = $envRepository->get('VAULT_KEY_PREFIX');
            $envRepository->set('VAULT_KEY_PREFIX', $vaultDefaultPrefix.'/'.$tenantId);

            (new LoadEnvironmentVariablesVault)->bootstrap($app);
        } catch (Throwable $e) {
            // preparing the logs for exception
            $app->instance('config', $config = new Repository([]));

            throw $e;
        }
    }
});
````

#### Alternative way


To enable this feature override the array of bootstrappers on _app/Console/Kernel.php_ and _app/Http/Kernel.php_ files
**app/Console/Kernel.php**
```php
use LaravelVault\LoadEnvironmentVariablesVault;

class Kernel extends ConsoleKernel
{
    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        LoadEnvironmentVariables::class,
        LoadEnvironmentVariablesVault::class, // <-- added custom bootstrapper 
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterFacades::class,
        SetRequestForConsole::class,
        RegisterProviders::class,
        BootProviders::class,
    ];
```


**app/Http/Kernel.php**

```php
use LaravelVault\LoadEnvironmentVariablesVault;

class Kernel extends HttpKernel
{
    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        LoadEnvironmentVariables::class,
        LoadEnvironmentVariablesVault::class,
        LoadConfiguration::class,
        HandleExceptions::class,
        RegisterFacades::class,
        RegisterProviders::class,
        BootProviders::class,
    ];
```

### HasSecret Eloquent Trait Usage

Add use trait to module 

```php
<?php

namespace App\Models;

use LaravelVault\Models\Traits\HasSecret;

class User extends Authenticatable
{
    use HasSecret;
    
    public function getSecretKeyAttribute(): string
    {
        return "{$this->id}";
    }


    /**
     * @return string
     */
    public function getSecretPrefix(): string
    {
        return "users/user_{$this->id}";
    }
}
```

Store the secret to User's model

```php 

use App\Models\User;


$user = User::find(1);

$user->secret = ['token' => 'secret_token_to_store']; // Stored on vault /secrets/users/user_1/1 

\Log::info('User\'s secret', $user->secret); // Will log : User\'s secret: ['token' => 'secret_token_to_store']   

```



### Performance 
Under investigation )) 

### TODO

- [ ] Tests implementation 
- [ ] Refactor the VaultClient to separate the methods by entrypoints to simplify the project development 

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
