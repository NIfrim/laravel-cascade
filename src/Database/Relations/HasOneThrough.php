<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Nifrim\LaravelCascade\Constants\Model;
use Nifrim\LaravelCascade\Concerns\AsHistorical;
use Illuminate\Database\Eloquent\Relations\Concerns\InteractsWithDictionary;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use Illuminate\Database\Eloquent\Relations\HasOneThrough as EloquentHasOneThrough;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough as EloquentHasOneOrManyThrough;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Nifrim\LaravelCascade\Models\Temporal
 * @template TResult
 */
class HasOneThrough extends HasOneOrManyThrough
{
    use InteractsWithDictionary, SupportsDefaultModels;

    /** @inheritDoc */
    public function getResults()
    {
        return $this->first() ?: $this->getDefaultFor($this->farParent);
    }

    /** @inheritDoc */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Overridden as we need to include the "updated at" column as part of the dictionary key.
     * 
     * @inheritDoc
     */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $temporalKey = method_exists($model, 'getUpdatedAt') ? $model->getUpdatedAt(true) : null;
            $dictionaryKey = "{$key}|{$temporalKey}";

            if (isset($dictionary[$dictionaryKey])) {
                $value = $dictionary[$dictionaryKey];
                $model->setRelation(
                    $relation,
                    reset($value)
                );
            }
        }

        return $models;
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param  TDeclaringModel  $parent
     * @return TRelatedModel
     */
    public function newRelatedInstanceFor(EloquentModel $parent)
    {
        return $this->related->newInstance();
    }
}
