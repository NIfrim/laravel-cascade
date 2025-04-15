<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Models\Temporal;

/**
 * @property int $id
 * @property string $name
 * @property int $valid_from
 * @property int $valid_to
 * @property int $created_at
 */
class DummyTemporal extends Temporal
{
    /**
     * Table name
     * @var string
     */
    protected $table = 'dummy_temporal';

    /**
     * @var string[]
     */
    protected $fillable = ['id', 'name'];
}
