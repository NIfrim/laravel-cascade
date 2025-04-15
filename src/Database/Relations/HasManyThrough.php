<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Nifrim\LaravelCascade\Models\Common;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Nifrim\LaravelCascade\Models\Temporal
 * @template TResult
 */
class HasManyThrough extends HasOneOrManyThrough
{
    use InteractsWithDictionary;

    /**
     * Convert the relationship to a "has one through" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<TRelatedModel, TIntermediateModel, TDeclaringModel>
     */
    public function one()
    {
        return HasOneThrough::noConstraints(fn() => new HasOneThrough(
            tap($this->getQuery(), fn(EloquentBuilder $query) => $query->getQuery()->joins = []),
            $this->farParent,
            $this->throughParent,
            $this->getFirstKeyName(),
            $this->secondKey,
            $this->getLocalKeyName(),
            $this->getSecondLocalKeyName(),
        ));
    }

    /** @inheritDoc */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /** @inheritDoc */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);
        $useTemporalAttributes = Common::canUseTemporalAttributes($models, $results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $temporalKey = method_exists($model, 'getUpdatedAt') && $useTemporalAttributes ? $model->getUpdatedAt(true) : null;
            $dictionaryKey = "{$key}|{$temporalKey}";

            if (isset($dictionary[$dictionaryKey])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$dictionaryKey])
                );
            }
        }

        return $models;
    }

    /** @inheritDoc */
    public function getResults()
    {
        return ! is_null($this->farParent->{$this->localKey})
            ? $this->get()
            : $this->related->newCollection();
    }
}
