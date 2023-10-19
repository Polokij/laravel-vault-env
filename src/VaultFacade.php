<?php

namespace LaravelVault;

use Illuminate\Support\Facades\Facade;

class VaultFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'vault';
    }
}
