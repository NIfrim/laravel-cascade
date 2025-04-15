<?php

namespace Nifrim\LaravelCascade\Concerns;

use Nifrim\LaravelCascade\Concerns\HasAssociations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;

trait HasAttributes
{
    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     *
     * @param  array|string|null  $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        $isDirtySelf = parent::isDirty($attributes);
        $hasDirtyRelations = false;

        if (in_array(HasAssociations::class, class_uses_recursive($this))) {
            foreach ($this->getAssociations() as $association) {
                $models = $association->data instanceof EloquentModel ? new Collection([$association->data]) : $association->data;
                $relationAttributes = isset($attributes[$association->name]) ? $attributes[$association->name] : [];
                if ($association->canDelete) {
                    $hasDirtyRelations = true;
                    break;
                }

                if ($models) {
                    foreach ($models->all() as $model) {
                        if ($hasDirtyRelations = $model->hasChanges($model->getDirty(), $relationAttributes)) {
                            break;
                        }
                    }
                }
            }
        }

        return $isDirtySelf || $hasDirtyRelations;
    }

    /**
     * Determine if any of the given attributes were changed when the model was last saved.
     *
     * @param  array  $changes
     * @param  array|string|null  $attributes
     * @return bool
     */
    protected function hasChanges($changes, $attributes = null)
    {
        // If no specific attributes were provided, we will just see if the dirty array
        // already contains any attributes. If it does we will just return that this
        // count is greater than zero. Else, we need to check specific attributes.
        if (empty(array_filter($attributes))) {
            return count($changes) > 0;
        }

        // Here we will spin through every attribute and see if this is in the array of
        // dirty attributes. If it is, we will return true and if we make it through
        // all of the attributes for the entire array we will return false at end.
        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }
}
