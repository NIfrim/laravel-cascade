<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\Temporal;

/**
 * @see \Nifrim\LaravelCascade\Models\Temporal
 */
class LaravelCascadeTemporalModel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Temporal::class;
    }
}
