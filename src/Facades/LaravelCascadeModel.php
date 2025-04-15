<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\Base;

/**
 * @see \VendorName\Skeleton\Skeleton
 */
class LaravelCascadeModel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Base::class;
    }
}
