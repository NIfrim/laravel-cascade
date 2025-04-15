<?php

namespace Nifrim\LaravelCascade\Database\Relations;

use Nifrim\LaravelCascade\Constants\Model;
use Nifrim\LaravelCascade\Database\Relations\Concerns\InteractsWithPivotTable;
use Nifrim\LaravelCascade\Models\BasePivot;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Nifrim\LaravelCascade\Models\Common;

class BelongsToMany extends EloquentBelongsToMany
{
    use InteractsWithPivotTable;

    /**
     * Indicates if soft deletes are available on the pivot table.
     *
     * @var bool
     */
    public $withSoftDeletes = false;

    /**
     * The custom pivot table column for the deleted_at timestamp.
     *
     * @var string
     */
    protected $pivotDeletedAt;

    /**
     * The custom pivot model.
     * 
     * @var BasePivot
     */
    protected $pivotModel;

    /**
     * @inheritDoc
     * @param  string  $relationName
     * @return void
     */
    public function __construct(
        EloquentBuilder $query,
        EloquentModel $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName,
    ) {
        if ($association = $parent->getAssociation($relationName)) {
            if ($association->pivot) {
                $using = $association->pivot->getModelClass();
                $this->query = $query;
                $this->using = $using;
                $this->pivotModel = $parent->newPivot($parent, [], $table, true, $using);
                if ($this->pivotModel->usesTimestamps() && $this->pivotModel->getDeletedAtColumn()) {
                    $this->pivotDeletedAt = $this->pivotModel->getDeletedAtColumn();
                    $this->pivotUpdatedAt = $this->pivotModel->getUpdatedAtColumn();
                    $this->withSoftDeletes();
                }
            }
        }

        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function deletedAt()
    {
        return $this->pivotDeletedAt;
    }

    /**
     * Get the fully qualified deleted at column name.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumnName()
    {
        return $this->getQualifiedColumnName($this->deletedAt());
    }

    /**
     * Get the fully qualified column name.
     *
     * @param string $column
     * @return string
     */
    public function getQualifiedColumnName($column)
    {
        return $this->table . '.' . $column;
    }

    /** @inheritDoc */
    public function match(array $models, EloquentCollection $results, $relation)
    {
        // First we'll build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to the
        // parents without having a possibly slow inner loop for every model.
        $dictionary = [];
        $accessor = $this->accessor;
        $useTemporalAttributes = Common::canUseTemporalAttributes($models, $results);

        foreach ($results as $result) {
            $key = $this->getDictionaryKey($result->{$accessor}->{$this->foreignPivotKey});
            $temporalValue = method_exists($result->{$accessor}, 'getUpdatedAt') && $useTemporalAttributes
                ? $result->{$accessor}->getUpdatedAt(true)
                : null;
            $dictionaryKey = "{$key}|{$temporalValue}";
            $dictionary[$dictionaryKey][] = $result;
        }

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // parent models. Then we should return these hydrated models back out.
        foreach ($models as $model) {
            $key = $this->getDictionaryKey($model->{$this->parentKey});
            $temporalKey = method_exists($model, 'getUpdatedAt') && $useTemporalAttributes
                ? $model->getUpdatedAt(true)
                : null;
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

    /**
     * @inheritDoc
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;
        $related = $this->getRelated();

        // If there are no `updated at` columns, let the parent handle the join.
        if (!$this->pivotUpdatedAt || !$related->getUpdatedAtColumn()) {
            return parent::performJoin($query);
        }

        // Othwerwise include the `updated at` column clause.
        $query->join($this->table, function ($join) use ($related) {
            $join
                ->on($this->getQualifiedRelatedKeyName(), '=', $this->getQualifiedRelatedPivotKeyName())
                ->on($related->getQualifiedUpdatedAtColumn(), '=', $this->qualifyPivotColumn($this->pivotUpdatedAt));
        });

        return $this;
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
                // Update the parent records first
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

                // Update the pivots second
                $records
                    ->filter(
                        fn($record) => isset($record->pivot)
                            && !$record->pivot->isIgnoringTouch()
                            && $parent->getUpdatedAt()->notEqualTo(
                                $record->pivot->getUpdatedAt()
                            )
                    )
                    ->each(
                        fn($record) => $record->pivot
                            ->setUpdatedAt($parent->getUpdatedAt())
                            ->save()
                    );
            });
        }
    }

    /**
     * Method defines soft delete macros and pivot columns
     */
    public function withSoftDeletes()
    {
        if (!$this->pivotModel || !$this->pivotDeletedAt) {
            return $this;
        }

        $this->macro('withoutTrashedPivots', function () {
            $this->withSoftDeletes = true;
            $this->query->withGlobalScope('withoutTrashedPivots', function (EloquentBuilder $query) {
                $query->where($this->getQualifiedDeletedAtColumnName(), '=', Model::END_OF_TIME);
            })->withoutGlobalScopes(['onlyTrashedPivots']);

            return $this;
        });

        $this->macro('onlyTrashedPivots', function () {
            $this->withSoftDeletes = false;
            $this->query->withGlobalScope('onlyTrashedPivots', function (EloquentBuilder $query) {
                $query->where($this->getQualifiedDeletedAtColumnName(), '<', Model::END_OF_TIME);
            })->withoutGlobalScopes(['withoutTrashedPivots']);

            return $this;
        });

        return $this->withPivot($this->deletedAt())->withoutTrashedPivots();
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
