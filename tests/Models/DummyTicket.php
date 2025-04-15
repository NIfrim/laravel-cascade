<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;
use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Models\TemporalPivot;

/**
 * @property string $user_id
 * @property string $flight_id
 * @property string $destination_id
 * @property ?Carbon $valid_from
 * @property ?Carbon $valid_to
 * @property ?Carbon $created_at
 */
class DummyTicket extends TemporalPivot
{
    protected $table = 'dummy_ticket';

    protected $fillable = [];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            Association::belongsToOne('flight', [
                'modelClass' => DummyFlight::class,
                'foreignKey' => 'flight_id',
                'onDelete' => AssociationActionType::CASCADE,
            ]),
            Association::belongsToOne('user', [
                'modelClass' => DummyUser::class,
                'foreignKey' => 'user_id',
                'onDelete' => AssociationActionType::CASCADE,
            ]),
        ]);
    }
}
