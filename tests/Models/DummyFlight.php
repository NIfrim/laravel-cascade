<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Models\Temporal;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @phpstan-import-type TAssociation from Nifrim\LaravelCascade\Concerns\HasAssociations
 * 
 * @property int $id
 * @property int $destination_id
 * @property string $title
 * @property int $valid_from
 * @property int $valid_to
 * @property int $created_at
 */
class DummyFlight extends Temporal
{
    protected $table = 'dummy_flight';

    /**
     * @var string[]
     */
    protected $fillable = ['id', 'destination_id', 'title'];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            Association::belongsToOne('destination', [
                'modelClass' => DummyDestination::class,
                'foreignKey' => 'destination_id',
                'onDelete' => AssociationActionType::SET_NULL,
            ]),

            Association::belongsToMany('users', [
                'modelClass'    => DummyUser::class,
                'foreignKey'    => 'flight_id',
            ], [
                'tableName'     => 'dummy_ticket',
                'modelClass'    => DummyTicket::class,
                'relationClass' => BelongsTo::class,
                'foreignKey'    => 'user_id',
                'onDelete'      => AssociationActionType::CASCADE,
            ]),
        ]);
    }
}
