<?php

namespace Nifrim\LaravelCascade\Models;

use Carbon\Carbon;
use Nifrim\LaravelCascade\Constants\Model;
use Nifrim\LaravelCascade\Database\Casts\UnixTimestamp;
use Nifrim\LaravelCascade\Concerns\AsHistorical;

/**
 * @phpstan-type TTemporalConfig array{start_column: string|numeric, end_column: string|numeric, max_timestamp: string|numeric}
 * 
 * @property int $id
 * @property ?Carbon $valid_from
 * @property ?Carbon $valid_to
 * @property ?Carbon $created_at
 */
class TemporalPivot extends BasePivot
{
    use AsHistorical;

    public $incrementing = false;

    protected $dates = ['valid_from', 'valid_to', 'created_at'];

    public $timestamps = true;
    public $withTimestamps = true;
    const DELETED_AT = 'valid_to';
    const UPDATED_AT = 'valid_from';
    const CREATED_AT = 'created_at';

    protected $attributes = [
        'valid_to' => Model::END_OF_TIME,
    ];

    protected $casts = [
        'valid_from' => UnixTimestamp::class,
        'valid_to' => UnixTimestamp::class,
        'created_at' => UnixTimestamp::class,
    ];

    /**
     * @inheritDoc
     */
    public function getCacheKey(?string $key = null): string
    {
        return get_class($this) . "|" . $this->{$this->foreignKey} . "|" . $this->{$this->relatedKey} . "|" . $this->getUpdatedAt(true);
    }

    /**
     * Determine if the pivot model or given attributes has timestamp attributes.
     *
     * @param  array|null  $attributes
     * @return bool
     */
    public function hasTimestampAttributes($attributes = null)
    {
        return true;
    }
}
