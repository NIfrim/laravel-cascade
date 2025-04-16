<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Models\BasePivot;

/**
 * @see \Nifrim\LaravelCascade\Models\BasePivot
 */
class LaravelCascadePivot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return BasePivot::class;
    }
}
