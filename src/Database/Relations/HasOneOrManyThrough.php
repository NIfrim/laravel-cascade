<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Nifrim\LaravelCascade\Concerns\AsHistorical;
use Nifrim\LaravelCascade\Constants\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough as EloquentHasOneOrManyThrough;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TIntermediateModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Nifrim\LaravelCascade\Models\Temporal
 * @template TResult
 */
abstract class HasOneOrManyThrough extends EloquentHasOneOrManyThrough
{
    /**
     * Overriden as we need to include the "updated at" column as part of the dictionary key.
     * 
     * @inheritDoc
     */
    protected function buildDictionary(EloquentCollection $results)
    {
        $dictionary = [];

        // First we will create a dictionary of models keyed by the foreign key of the
        // relationship as this will allow us to quickly access all of the related
        // models without having to do nested looping which will be quite slow.
        foreach ($results as $result) {
            $key = $result->laravel_through_key;
            $temporalKey = method_exists($result, 'getUpdatedAt') ? $result->getUpdatedAt(true) : null;

            $dictionaryKey = "{$key}|{$temporalKey}";
            $dictionary[$dictionaryKey][] = $result;
        }

        return $dictionary;
    }

    /**
     * Overriden as we need to include the "updated at" column in case of a temporal model.
     * 
     * @inheritDoc
     */
    protected function performJoin(?EloquentBuilder $query = null)
    {
        $query = $query ?: $this->query;
        $parent = $this->getParent();
        $related = $this->getRelated();

        // If there are no `updated at` columns, let the parent handle the join.
        if (!$parent->getUpdatedAtColumn() || !$related->getUpdatedAtColumn()) {
            return parent::performJoin($query);
        }

        // Othwerwise include the `updated at` column clause.
        $query->join($this->throughParent->getTable(), function ($join) use ($parent, $related) {
            $join
                ->on($this->getQualifiedParentKeyName(), '=', $this->getQualifiedFarKeyName())
                ->on($parent->qualifyColumn($parent->getUpdatedAtColumn()), '=', $related->qualifyColumn($related->getUpdatedAtColumn()));
        });

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRelationExistenceQueryForThroughSelfRelation(EloquentBuilder $query, EloquentBuilder $parentQuery, $columns = ['*'])
    {
        $table = $this->throughParent->getTable() . ' as ' . $hash = $this->getRelationCountHash();

        $query->join($table, $hash . '.' . $this->secondLocalKey, '=', $this->getQualifiedFarKeyName());

        if ($this->throughParentSoftDeletes()) {
            $query->where($hash . '.' . $this->throughParent->getDeletedAtColumn(), '=', Model::END_OF_TIME);
        }

        return $query->select($columns)->whereColumn(
            $parentQuery->getQuery()->from . '.' . $this->localKey,
            '=',
            $hash . '.' . $this->firstKey
        );
    }

    /**
     * @inheritDoc
     */
    public function getRelationExistenceQueryForSelfRelation(EloquentBuilder $query, EloquentBuilder $parentQuery, $columns = ['*'])
    {
        $query->from($query->getModel()->getTable() . ' as ' . $hash = $this->getRelationCountHash());

        $query->join($this->throughParent->getTable(), $this->getQualifiedParentKeyName(), '=', $hash . '.' . $this->secondKey);

        if ($this->throughParentSoftDeletes()) {
            $query->where($this->throughParent->getQualifiedDeletedAtColumn(), '=', Model::END_OF_TIME);
        }

        $query->getModel()->setTable($hash);

        return $query->select($columns)->whereColumn(
            $parentQuery->getQuery()->from . '.' . $this->localKey,
            '=',
            $this->getQualifiedFirstKeyName()
        );
    }

    /**
     * Overridden as we do soft deletes differently.
     * 
     * @inheritDoc
     */
    public function throughParentSoftDeletes()
    {
        return in_array(AsHistorical::class, class_uses_recursive($this->throughParent));
    }
}
