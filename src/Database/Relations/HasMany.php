<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Nifrim\LaravelCascade\Models\Common;

class HasMany extends EloquentHasMany
{
    /** @inheritDoc */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $foreign = $this->getForeignKeyName();
        $useTemporalAttributes = Common::canUseTemporalAttributes($models, $results);

        $dictionary = $results->mapToDictionary(function ($result) use ($foreign, $useTemporalAttributes) {
            $temporalKey = method_exists($result, 'getUpdatedAt') && $useTemporalAttributes
                ? $result->getUpdatedAt(true)
                : null;

            $dictionaryKey = "{$result->{$foreign}}|{$temporalKey}";
            return [$this->getDictionaryKey($dictionaryKey) => $result];
        })->all();

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $temporalKey = method_exists($model, 'getUpdatedAt') && $useTemporalAttributes
                ? $model->getUpdatedAt(true)
                : null;

            $dictionaryKey = "{$model->getAttribute($this->localKey)}|{$temporalKey}";
            if (isset($dictionary[$dictionaryKey])) {
                $related = $this->getRelationValue($dictionary, $dictionaryKey, 'many');
                $model->setRelation($relation, $related);

                // Apply the inverse relation if we have one...
                $this->applyInverseRelationToCollection($related, $model);
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
        $related = $this->getRelated();
        $parent = $this->getParent();

        if (! $related::isIgnoringTouch()) {
            // Update the related timestamp to match that of the parent
            tap($this->get(), function ($records) use ($parent) {
                $records
                    ->filter(
                        fn($record) => $parent
                            ->getUpdatedAt()
                            ->notEqualTo($record->getUpdatedAt())
                    )
                    ->each(
                        fn($record) => $record
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
