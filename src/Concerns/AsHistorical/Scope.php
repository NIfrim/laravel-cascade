<?php

namespace Nifrim\LaravelCascade\Concerns\AsHistorical;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Nifrim\LaravelCascade\Models\Temporal as Model;
use Carbon\Carbon;

class Scope extends SoftDeletingScope
{
    protected Builder $builder;

    /**
     * All of the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected $extensions = ['TrashedBetween', 'TrashedOn', 'WithTrashed', 'WithoutTrashed', 'OnlyTrashed'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder  $builder
     * @param  Model  $model
     * @return void
     */
    public function apply($builder, $model)
    {
        $temporalConfig = $model->temporalConfig();
        $builder->where($model->getQualifiedDeletedAtColumn(), $temporalConfig['max_timestamp']);
    }

    /**
     * Add the trashed-on extension to the builder.
     * 
     * @param Builder $builder
     * @return void
     */
    protected function addTrashedOn($builder)
    {
        $builder->macro('trashedOn', function ($builder, ?Carbon $timestamp = null) {
            $model = $builder->getModel();
            $temporalConfig = $model->temporalConfig();
            $builder->onlyTrashed();

            $timestamp = $timestamp ?: $model->freshTimestamp();
            return $builder->where($temporalConfig['start_column'], '=', $timestamp->getTimestampMs());
        });
    }

    /**
     * Add the trashed-between extension to the builder.
     * 
     * @param Builder $builder
     * @return void
     */
    protected function addTrashedBetween($builder)
    {
        $builder->macro('trashedBetween', function ($builder, Carbon $from, Carbon $to, mixed $id = null) {
            $model = $builder->getModel();
            $temporalConfig = $model->temporalConfig();
            $builder->onlyTrashed();

            if ($id) {
                $builder->where($model->getKeyName(), $id);
            }

            return $builder
                ->where($temporalConfig['start_column'], '<=', $to->getTimestampMs())
                ->where($temporalConfig['end_column'], '>=', $from->getTimestampMs())
                ->where($temporalConfig['end_column'], '<', $temporalConfig['max_timestamp']);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addWithoutTrashed($builder)
    {
        $builder->macro('withoutTrashed', function ($builder) {
            $model = $builder->getModel();
            $temporalConfig = $model->temporalConfig();

            $builder->withoutGlobalScope($this)->where(
                $model->getQualifiedDeletedAtColumn(),
                $temporalConfig['max_timestamp']
            );

            return $builder;
        });
    }

    /**
     * Add the only-trashed extension to the builder.
     *
     * @param  Builder  $builder
     * @return void
     */
    protected function addOnlyTrashed($builder)
    {
        $builder->macro('onlyTrashed', function ($builder) {
            $model = $builder->getModel();
            $temporalConfig = $model->temporalConfig();

            $builder->withoutGlobalScope($this)->where(
                $model->getQualifiedDeletedAtColumn(),
                '<',
                $temporalConfig['max_timestamp']
            );

            return $builder;
        });
    }
}
