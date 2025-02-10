<?php

namespace LaravelVault\Models\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LaravelVault\Facades\Vault;

/**
 * The trait provide the get,put secrets features for Eloquent models
 *
 * @property $secretPrefix string
 * @property $secret       array
 */
trait HasSecret
{
    /**
     * @var array|null
     */
    private array|null $vaultSecrets = [];


    /**
     * @return void
     */
    public static function bootHasSecret(): void
    {
        static::creating(function (self $model) {
            if ($model->secret_key) {
                return;
            }

            $model->secret_key = $model->generateSecretKeyTrait();
        });

        static::created(function (self $model) {
            if (!empty($model->hasSecret())) {
                $model->storeSecret('', $model->vaultSecrets);
            }
        });


        if (!\in_array(SoftDeletes::class, \class_uses_recursive(self::class))) {
            static::deleted(function (self $model) {
                $model->destroySecret('');
            });
        } else {
            static::forceDeleted(function (self $model) {
                $model->destroySecret('');
            });
        }
    }


    /**
     * @return bool
     */
    public function hasSecret()
    {
        return (bool)$this->vaultSecrets;
    }


    /**
     * @return array|mixed|null
     */
    public function getSecretAttribute(): array|null
    {
        if (!empty($this->vaultSecrets)) {
            return $this->vaultSecrets;
        }

        return $this->vaultSecrets = $this->getSecret();
    }


    /**
     * @param array $value
     *
     * @return false|void
     */
    public function setSecretAttribute(array $value)
    {
        if (!$this->secret_key) {
            $this->secret_key = $this->generateSecretKeyTrait();
        }

        // skip saving the key if the model wasn't saved yet
        if (!$this->exists) {
            $this->vaultSecrets = $value;

            return;
        }

        $res = $this->storeSecret('', $value);

        if ($res) {
            $this->vaultSecrets = $value;
        }

        if ($this->isDirty('secret_key')) {
            $this->save();
        }
    }


    /**
     * @param string $key
     * @param array|null $value
     *
     * @return array|bool|null
     */
    public function secret(string $key = '', array $value = null): array|bool|null
    {
        if (!$value) {
            return $this->getSecret($key);
        }

        return $this->storeSecret($key, $value);
    }


    /**
     * @return string
     */
    public function getSecretPath($key = ''): string
    {
        if (!empty($this->secretPrefix)) {
            $prefix = $this->secretPrefix;
        } elseif (method_exists($this, 'getSecretPrefix')) {
            $prefix = $this->getSecretPrefix();
        }

        $secretKey = $this->secret_key ?: $this->id;

        $key = $secretKey.($key ? "/$key" : '');

        if (!empty($prefix)) {
            return "$prefix/$key";
        }

        return $key ?: '';
    }


    /**
     * @param string $key
     *
     * @return array|null
     */
    public function getSecret(string $key = ''): array|null
    {
        Vault::setThrowException(false);
        $secrets = Vault::secret($this->getSecretPath($key));

        return isset($secrets['errors']) ? null : $secrets;
    }


    /**
     * @param string $key
     * @param array $value
     *
     * @return bool
     */
    public function storeSecret(string $key, array $value): bool
    {
        Vault::setThrowException(true);
        Vault::setTimeout(5);
        $result = Vault::secret($this->getSecretPath($key), $value);

        return !isset($result['errors']);
    }


    /**
     * @param string $key
     *
     * @return bool
     */
    public function destroySecret(string $key = ''): bool
    {
        Vault::setThrowException(false);
        if (!$key || Str::endsWith('/', $key)) {
            collect($this->listSecrets($key))
                ->each(function ($key, $index) {
                    $this->destroySecret($key);
                });
        }

        // TODO:; Update the vault package to generate the path automatically
        $path = config('vault.storage').'/metadata/'.$this->getSecretPath($key);

        $result = Vault::delete($path);

        return !isset($result['errors']);
    }


    /**
     * @param $key
     *
     * @return array
     */
    public function listSecrets($key = ''): array
    {
        $path = config('vault.storage').'/metadata/'.$this->getSecretPath($key);

        return Vault::listRequest($path)['keys'] ?? [];
    }


    /**
     * @return string
     */
    public function generateSecretKeyTrait(): string
    {
        if (\method_exists($this, 'generateSecretKey')) {
            return $this->generateSecretKey();
        }

        return Str::uuid();
    }
}
