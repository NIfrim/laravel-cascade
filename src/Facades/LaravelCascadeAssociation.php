<?php

namespace Nifrim\LaravelCascade\Facades;

use Illuminate\Support\Facades\Facade;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;

/**
 * @see \Nifrim\LaravelCascade\Concerns\HasAssociations\Association
 */
class LaravelCascadeAssociation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Association::class;
    }
}
