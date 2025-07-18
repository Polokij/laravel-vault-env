<?php

namespace LaravelVault\Facades;

use Illuminate\Support\Facades\Facade;

class Vault extends Facade
{

    /**
     * Get or create the singleton alias loader instance.
     *
     * @param  array  $aliases
     * @return \Illuminate\Foundation\AliasLoader
     */
    public static function getInstance(array $aliases = [])
    {
        return app('vault');
    }

    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'vault';
    }
}
