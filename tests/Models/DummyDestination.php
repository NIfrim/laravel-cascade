<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Models\Temporal;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;

/**
 * @phpstan-import-type TAssociation from Nifrim\LaravelCascade\Concerns\HasAssociations
 * 
 * @property int $id
 * @property string $title
 * @property int $valid_from
 * @property int $valid_to
 * @property int $created_at
 */
class DummyDestination extends Temporal
{
    protected $table = 'dummy_destination';

    /**
     * @var string[]
     */
    protected $fillable = ['id', 'title'];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            Association::hasMany('flights', [
                'modelClass' => DummyFlight::class,
                'foreignKey' => 'destination_id',
            ]),
        ]);
    }
}
