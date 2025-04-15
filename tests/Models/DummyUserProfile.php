<?php

namespace Nifrim\LaravelCascade\Tests\Models;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Models\Temporal;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $email
 */
class DummyUserProfile extends Temporal
{
    protected $table = 'dummy_user_profile';

    /**
     * @var string[]
     */
    protected $fillable = ['id', 'user_id', 'name', 'email'];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            Association::belongsToOne('user', [
                'modelClass' => DummyUser::class,
                'foreignKey' => 'user_id',
                'onDelete' => AssociationActionType::CASCADE,
            ]),
        ]);
    }
}
