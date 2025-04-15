<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Nifrim\LaravelCascade\Models\Common;

class BelongsTo extends EloquentBelongsTo
{
    /** @inheritDoc */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];
        $useTemporalAttribute = Common::canUseTemporalAttributes($models, $results);

        foreach ($results as $result) {
            $attribute = $this->getDictionaryKey($this->getRelatedKeyFrom($result));
            $temporalAttribute = method_exists($result, 'getUpdatedAt') && $useTemporalAttribute
                ? $result->getUpdatedAt(true)
                : null;
            $dictionaryKey = "{$attribute}|{$temporalAttribute}";

            $dictionary[$dictionaryKey] = $result;
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            $attribute = $this->getDictionaryKey($this->getForeignKeyFrom($model));
            $temporalAttribute = method_exists($model, 'getUpdatedAt') && $useTemporalAttribute
                ? $model->getUpdatedAt(true)
                : null;
            $dictionaryKey = "{$attribute}|{$temporalAttribute}";

            if (isset($dictionary[$dictionaryKey])) {
                $model->setRelation($relation, $dictionary[$dictionaryKey]);
            }
        }

        return $models;
    }

    /**
     * Overridden as we want to pass value through attribute casting.
     * 
     * @inheritDoc
     */
    public function touch()
    {
        $related = $this->getRelated();         // The parent model to which this relation points to
        $parent = $this->getParent();           // This record which has the `belongsTo` relation

        if (! $related::isIgnoringTouch()) {
            // Update the related timestamp to match that of the parent
            tap($this->get(), function ($records) use ($parent, $related) {
                // Update the related timestamp to match that of the parent
                $records
                    ->filter(
                        fn($record) =>
                        $parent->getUpdatedAt()->notEqualTo($record->getUpdatedAt())
                    )
                    ->each(
                        fn($record) =>
                        $record
                            ->setUpdatedAt($parent->getUpdatedAt())
                            ->save()
                    );
            });
        }
    }

    /**
     * Overriden to include `updated at` column clause.
     * 
     * @inheritDoc
     */
    public function getRelationExistenceQuery($query, $parentQuery, $columns = ['*'])
    {
        $query = parent::getRelationExistenceQuery($query, $parentQuery, $columns);

        // Add the start column as part of the query if both the related and the parent have a start column set
        $related = $this->getRelated();
        $relatedUpdatedAtColumn = $related->getUpdatedAtColumn();

        $parent = $this->getParent();
        $parentUpdatedAtColumn = $parent->getUpdatedAtColumn();

        if ($relatedUpdatedAtColumn && $parentUpdatedAtColumn) {
            $query->whereColumn(
                $related->qualifyColumn($relatedUpdatedAtColumn),
                '=',
                $parent->qualifyColumn($parentUpdatedAtColumn)
            );
        }

        return $query;
    }
}
