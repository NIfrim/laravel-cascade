<?php

namespace Nifrim\LaravelCascade\Concerns;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Concerns\HasAssociations\Association;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @phpstan-import-type TRelationClass from \Nifrim\LaravelCascade\Concerns\HasAssociations\Association 
 * @phpstan-import-type TAssociationOptions from \Nifrim\LaravelCascade\Concerns\HasAssociations\Association 
 * @phpstan-type TAssociation array{name: string, relationClass: TRelationClass, options: TAssociationOptions}
 */
trait HasAssociations
{
    /**
     * @var Association[]
     */
    protected array $associations = [];

    public static function bootHasAssociations()
    {
        static::saved(function ($model) {
            $model->cascadeAction();
        });

        static::deleted(function ($model) {
            $model->cascadeAction(true);
        });
    }

    /**
     * Initialize the model associations by generating the relation resolvers.
     *
     * @return static
     */
    public function initializeHasAssociations(): static
    {
        $this->setAssociations();
        // Generate the resolvers for each association
        foreach ($this->getAssociations() as $association) {
            // $association->setTouches($this);
            $association->setResolver($this);
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        // Fill the model and associations attributes
        parent::fill($attributes);
        if ($attributes) {
            $this->setAssociationsData($attributes);
        }

        return $this;
    }

    /**
     * Retrieve all associations.
     *
     * @return Association[]
     */
    public function getAssociations(): array
    {
        return $this->associations ?: [];
    }

    /**
     * Get a specific association by name.
     *
     * @param string $associationName
     * @return ?Association
     */
    public function getAssociation(string $associationName): ?Association
    {
        foreach ($this->getAssociations() as $association) {
            if ($association->options['modelClass'] === $associationName) {
                return $association;
            }

            if ($association->name === $associationName) {
                return $association;
            }
        }
        return null;
    }

    /**
     * Set the associations data for the model.
     *
     * Override this method in your model to define associations.
     *
     * @param array $attributes
     * 
     * @return static
     */
    public function setAssociationsData(array $attributes): static
    {
        // Set the associations data if attributes provided
        foreach ($this->getAssociations() as $association) {
            $associationData = data_get($attributes, $association->name, []);
            $canDeleteIfNoData = isset($attributes[$association->name]);
            $association->setData($this, $associationData, $canDeleteIfNoData);
            $this->setRelation($association->name, $association->data);
        }
        return $this;
    }

    /**
     * Set the associations data for the model.
     *
     * Override this method in your model to define associations.
     *
     * @param Association[] $associations
     * 
     * @return static
     */
    public function setAssociations(array $associations = []): static
    {
        $this->associations = $associations;
        return $this;
    }

    /**
     * Cascade the action to the relations
     * 
     * @param bool $deleted
     */
    protected function cascadeAction(bool $deleted = false): void
    {
        // Cascade save logic for related models
        foreach ($this->getRelations() as $relationName => $models) {
            if ($association = $this->getAssociation($relationName)) {
                // $relationName = $association->name;
                $relationResolver = $this->{$relationName}();

                $models = $models instanceof EloquentModel ? collect([$models]) : $models;
                if (!$models) {
                    $models = new EloquentCollection([]);
                }

                // if ($association->data) {
                //     $models = $association->data instanceof EloquentModel ? new EloquentCollection([$association->data]) : $association->data;
                // }

                $pivotModels = new EloquentCollection([]);
                if ($association->pivot && $association->pivot->data) {
                    $pivotModels = $association->pivot->data instanceof EloquentModel ? new EloquentCollection([$association->pivot->data]) : $association->pivot->data;
                }

                if ($association->canDelete) {
                    $deleted = true;
                    $models = $relationResolver->get();
                }

                foreach ($models->all() as $index => $model) {
                    if ($relationResolver instanceof BelongsTo) {
                        if ($deleted) {
                            if ($association->options['onDelete'] === AssociationActionType::CASCADE) {
                                $relationResolver->dissociate();
                                $model->delete();
                            }
                            if ($association->options['onDelete'] === AssociationActionType::SET_NULL) {
                                $relationResolver->dissociate();
                            }
                            continue;
                        }

                        if (!$model->exists || $model->isDirty()) {
                            // if (!$model->exists) {
                            //     $model->setUpdatedAt($this->getUpdatedAt());
                            // }

                            $model->save();
                        }
                        $relationResolver->associate($model);
                        continue;
                    }

                    if ($relationResolver instanceof BelongsToMany) {
                        if ($deleted) {
                            if ($association->pivot->options['onDelete'] === AssociationActionType::CASCADE) {
                                $relationResolver->detach($model->getKey());
                            }
                            if ($association->pivot->options['onDelete'] === AssociationActionType::SET_NULL) {
                                $relationResolver->updateExistingPivot($model->getKey(), [$association->getForeignKey() => 0]);
                            }
                            continue;
                        }

                        if (!$model->exists || $model->isDirty()) {
                            // if (!$model->exists) {
                            //     $model->setUpdatedAt($this->getUpdatedAt());
                            // }

                            $model->save();
                        }

                        $pivotValues = [];
                        $pivotModel = isset($pivotModels[$index]) ? $pivotModels[$index] : null;
                        if ($pivotModel) {
                            foreach ($pivotModel->getFillable() as $key) {
                                $pivotValues[$key] = $pivotModel->{$key};
                            }
                        }

                        $relationResolver->syncWithPivotValues([$model->getKey()], $pivotValues);
                        continue;
                    }

                    if ($relationResolver instanceof HasOne || $relationResolver instanceof HasMany) {
                        // $model->setUpdatedAt($this->getUpdatedAt());
                        $model->{$association->getForeignKey()} = $this->{$association->getPrimaryKey()};
                        $model->save();
                        continue;
                    }
                }
            }
        }

        // $this->touchOwners();
    }

    /**
     * Recursively process all loaded associations history records.
     *
     * @param  Carbon      $updatedAt
     * @param  ?Carbon     $deletedAt 
     * @param  ?array      &$historyLog
     * @param  bool|null   $exists
     * 
     * @return void
     */
    public function cascadeUpdatingHistory(Carbon $updatedAt, ?Carbon $deletedAt = null, ?array &$historyLog = [], ?bool $exists = false): void
    {
        if (method_exists($this, 'updateAllHistory')) {
            foreach ($this->getAssociations() as $association) {
                $models = $this->{$association->name}()->get();

                if ($models instanceof EloquentModel) {
                    $models->updateAllHistory($updatedAt, $deletedAt, $historyLog, $exists);
                    continue;
                }

                if ($models instanceof EloquentCollection) {
                    foreach ($models->all() as $relatedModel) {
                        if ($relatedModel instanceof EloquentModel) {
                            $relatedModel->updateAllHistory($updatedAt, $deletedAt, $historyLog, $exists);
                        }
                    }
                }
            }
        }
    }
}
