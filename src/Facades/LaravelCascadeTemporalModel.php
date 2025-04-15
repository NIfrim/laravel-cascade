<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\Temporal;

/**
 * @see \VendorName\Skeleton\Skeleton
 */
class LaravelCascadeTemporalModel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Temporal::class;
    }
}
