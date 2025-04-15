<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\TemporalPivot;

/**
 * @see \VendorName\Skeleton\Skeleton
 */
class LaravelCascadeTemporalPivot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TemporalPivot::class;
    }
}
