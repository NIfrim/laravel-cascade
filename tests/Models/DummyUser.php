<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Models\Temporal;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property int $valid_from
 * @property int $valid_to
 * @property int $created_at
 */
class DummyUser extends Temporal
{
    protected $table = 'dummy_user';

    /**
     * @var string[]
     */
    protected $fillable = ['id', 'code'];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            // A User has one Profile.
            Association::hasOne('profile', [
                'modelClass' => DummyUserProfile::class,
                'foreignKey' => 'user_id'
            ]),

            // A User has many Flights.
            Association::belongsToMany('flights', [
                'modelClass'  => DummyFlight::class,
                'foreignKey'  => 'user_id',
            ], [
                'name'          => 'pivot',
                'tableName'     => 'dummy_ticket',
                'modelClass'    => DummyTicket::class,
                'relationClass' => BelongsTo::class,
                'foreignKey'    => 'flight_id',
                'onDelete'      => AssociationActionType::CASCADE,
            ]),
        ]);
    }
}
