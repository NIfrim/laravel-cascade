<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\Base;
use Nifrim\LaravelCascade\Models\BasePivot;

/**
 * @see \VendorName\Skeleton\Skeleton
 */
class LaravelCascadePivot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BasePivot::class;
    }
}
