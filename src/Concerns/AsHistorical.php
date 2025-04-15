<?php

namespace Nifrim\LaravelCascade\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;
use Nifrim\LaravelCascade\Constants\Model;
use Nifrim\LaravelCascade\Database\Casts\UnixTimestamp;
use Nifrim\LaravelCascade\Exceptions\InvalidArgumentException;
use Nifrim\LaravelCascade\Concerns\AsHistorical\Scope;
use Nifrim\LaravelCascade\Models\Base;
use Nifrim\LaravelCascade\Models\BasePivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @template TModel of \Nifrim\LaravelCascade\Models\Temporal
 * 
 * @phpstan-import-type TTemporalConfig from \Nifrim\LaravelCascade\Models\Temporal
 * 
 * @method static Builder<TModel> withTrashed(bool $withTrashed = true)
 * @method static Builder<TModel> onlyTrashed()
 * @method static Builder<TModel> trashedOn(?Carbon $timestamp = null)
 * @method static Builder<TModel> trashedBetween(Carbon $from, Carbon $to, mixed $id = null)
 * @method static Builder<TModel> withoutTrashed()
 * @method static restoreOrCreate(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 * @method static createOrRestore(array<string, mixed> $attributes = [], array<string, mixed> $values = [])
 */
trait AsHistorical
{
    /**
     * Indicates if the model is currently force deleting.
     *
     * @var bool
     */
    protected $forceDeleting = false;

    /**
     * Temporal properties (overridable in child models)
     * 
     * @var TTemporalConfig
     */
    protected static $temporalConfig = [
        'start_column'  => 'valid_from',
        'end_column'    => 'valid_to',
        'max_timestamp' => Model::END_OF_TIME,
    ];

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootAsHistorical(): void
    {
        static::addGlobalScope(new Scope);

        static::creating(function (Base $model) {
            // Ensure primary key is set if model has one defined.
            if ($model->getKeyName() && !$model->{$model->getKeyName()}) {
                $model->{$model->getKeyName()} = $model->getNextKey();
            }

            $dateNow = $model->freshTimestamp();

            // Ensure start column is set
            $startColumn = $model->getUpdatedAtColumn();
            if ($startColumn && !$model->getUpdatedAt()) {
                $model->setUpdatedAt($dateNow);
            }

            // Ensure created at is set if not already set
            $createdColumn = $model->getCreatedAtColumn();
            if ($createdColumn && !$model->getCreatedAt()) {
                $model->setCreatedAt($dateNow);
            }

            // Ensure pivots match the parent's "updated at" column.
            if ($model instanceof BasePivot && $model->getUpdatedAtColumn()) {
                $model->setUpdatedAt($model->pivotParent->getUpdatedAt());
            }
        });

        static::updating(function (Base $model) {
            $dateNow = $model->freshTimestamp();

            if ($model->isDirty()) {
                // Ensure start column is set
                $startColumn = $model->getUpdatedAtColumn();
                if ($startColumn) {
                    $model->setUpdatedAt($dateNow);
                }
            }

            // Ensure pivots match the parent's "updated at" column.
            if ($model instanceof BasePivot && $model->getUpdatedAtColumn()) {
                $model->setUpdatedAt($model->pivotParent->getUpdatedAt());
            }
        });

        static::saved(function (Base $model) {
            foreach ($model->getRelations() as $relationName => $records) {
                $resolverCallback = $model->relationResolver($model::class, $relationName);

                if ($resolverCallback) {
                    $resolverInstance = $resolverCallback($model);

                    $records = $records instanceof EloquentModel ? collect([$records]) : $records;
                    if (!$records) {
                        $records = $resolverInstance->get();
                    }

                    // Ensure related exist with the parent's "updated at" column.
                    if ($resolverInstance instanceof BelongsTo || $resolverInstance instanceof BelongsToMany) {

                        try {
                            $records->each(function (Base $related) use ($model) {
                                $updatedAtColumn = $related->getUpdatedAtColumn();
                                if ($model->getUpdatedAt() && $updatedAtColumn) {
                                    $relatedUpdatedAt = $related->getUpdatedAt();
                                    if (!$relatedUpdatedAt || $relatedUpdatedAt->notEqualTo($model->getUpdatedAt())) {
                                        $related->forceFill([$updatedAtColumn => $model->getUpdatedAt()])->save();
                                    }
                                }
                            });
                        } catch (\Throwable $th) {
                            //throw $th;
                        }
                    }

                    // Ensure related match the parent's "updated at" column.
                    if ($resolverInstance instanceof HasOne || $resolverInstance instanceof HasMany) {
                        $records->each(function ($related) use ($model) {
                            $updatedAtColumn = $related->getUpdatedAtColumn();

                            if ($model->getUpdatedAt() && $updatedAtColumn) {
                                $relatedUpdatedAt = $related->getUpdatedAt();

                                if (!$relatedUpdatedAt || $relatedUpdatedAt->notEqualTo($model->getUpdatedAt())) {
                                    $related->forceFill([$updatedAtColumn => $model->getUpdatedAt()])->save();
                                }
                            }
                        });
                    }
                }
            }
        });

        static::updating(function (Base $model) {
            $dateNow = $model->freshTimestamp();
            $model->setUpdatedAt($dateNow);
        });

        static::updated(function (Base $model) {
            $dateNow = $model->freshTimestamp();
            $model->updateAllHistory($model->getUpdatedAt(), $dateNow);
        });
    }

    /**
     * Initialize the trait for an instance.
     *
     * @return void
     */
    public function initializeAsHistorical(): void
    {
        if (!isset($this->casts[$this->getDeletedAtColumn()])) {
            $this->casts[$this->getDeletedAtColumn()] = UnixTimestamp::class;
        }

        if (!isset($this->casts[$this->getUpdatedAtColumn()])) {
            $this->casts[$this->getUpdatedAtColumn()] = UnixTimestamp::class;
        }
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        $temporalConfig = $this->temporalConfig();
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
        $query->where($temporalConfig['end_column'], '=', $temporalConfig['max_timestamp']);

        return $query;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        $constantName = static::class . '::CREATED_AT';
        return defined($constantName) ? constant($constantName) : '';
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        $constantName = static::class . '::UPDATED_AT';
        return defined($constantName) ? constant($constantName) : static::$temporalConfig['start_column'];
    }

    /**
     * Returns the start column value
     * 
     * @return ($inMiliseconds is true ? ?int : ?Carbon)
     */
    public function getUpdatedAt($inMiliseconds = false): null|int|Carbon
    {
        if ($this->{$this->getUpdatedAtColumn()}) {
            $columnValue = $this->{$this->getUpdatedAtColumn()};
            return $inMiliseconds ? $columnValue->getTimestampMs() : $columnValue;
        }
        return null;
    }

    /**
     * Manually generate the next value for the primary key.
     */
    protected function getNextKey(): null|string|int
    {
        $instance = new static;
        $keyType = $instance->getKeyType();
        if (!($keyName = $instance->getKeyName())) {
            return null;
        }

        if (in_array($keyType, ['int', 'integer'])) {
            return static::withTrashed()->max($keyName) + 1;
        }
        if (in_array($keyType, ['string'])) {
            return Str::uuid();
        }

        throw new InvalidArgumentException($keyType, ['string', 'int', 'integer']);
    }

    /**
     * Get the temporal config via instance
     */
    public static function temporalConfig()
    {
        return static::$temporalConfig;
    }

    /**
     * Method to create historic record with given overrides.
     * Useful when needed to clone a record as historic but with different attributes,
     * for example if the developer needs to store historic soft deleted record.
     * 
     * @param Carbon $updatedAt
     * @param ?Carbon $deletedAt
     * @param ?array $historyLog
     * @param ?bool $exists
     * 
     * @return self
     */
    public function saveHistoricRecord(Carbon $updatedAt, ?Carbon $deletedAt = null, ?array &$historyLog = [], ?bool $exists = false)
    {
        $historyLog[] = $this->getCacheKey();
        try {
            $updatedAtColumn = $this->getUpdatedAtColumn();
            $deletedAtColumn = $this->getDeletedAtColumn();
            $originalAttributes = collect($this->getOriginal())
                ->filter(fn($value, $key) => !Str::startsWith($key, 'pivot_'))
                ->all();

            // Update the touched records if timestamp provided and they are different than existing
            if ($updatedAt && method_exists($this, 'getUpdatedAt') && $this->getUpdatedAt()->notEqualTo($updatedAt)) {
                $this->setUpdatedAt($updatedAt);
                $historyLog[] = $this->getCacheKey();
                $this->saveQuietly();
            }

            // Save historic record without raising any events
            $saveHistoric = isset($originalAttributes[$updatedAtColumn])
                && $originalAttributes[$updatedAtColumn]->notEqualTo($updatedAt);

            if ($saveHistoric) {
                $fillAttributes = [
                    ...$originalAttributes,
                    ...($deletedAt ? [$deletedAtColumn => $deletedAt] : []),
                ];
                $historicalRecord = $this->replicate()->forceFill($fillAttributes);
                $historicalRecord->exists = $exists;
                $historyLog[] = $historicalRecord->getCacheKey();
                $historicalRecord->saveQuietly();
            }

            return $this;
        } catch (\Throwable $e) {
            // We catch in case historical record already exists,
            // should probably handle this via a logger.
            // dump($e->getCode(), $e->getMessage());
            // throw $e
        }

        return $this;
    }

    /**
     * Recursively save a historical record for this model, its pivot (if any), and all loaded relations.
     *
     * @param  Carbon  $updatedAt           The date of the "updated at" field.
     * @param  ?Carbon $deletedAt           If set it will also update records with a new timestamp.
     * @param  ?array  &$historyLog         Reference array to track touched models.
     * @param  ?bool   $exists              Whether the record should be marked as existing.
     * 
     * @return $this
     */
    public function updateAllHistory(Carbon $updatedAt, ?Carbon $deletedAt = null, ?array &$historyLog = [], ?bool $exists = false): self
    {
        if ($this->fireModelEvent('updatingHistory') === false) {
            return $this;
        }

        $historyLogKey = $this->getCacheKey();
        if (! in_array($historyLogKey, $historyLog)) {
            $this->saveHistoricRecord($updatedAt, $deletedAt, $historyLog, $exists);
            $this->updatePivotHistoricRecord($updatedAt, $deletedAt, $historyLog, $exists);
            $this->updateAssociationsHistoryRecords($updatedAt, $deletedAt, $historyLog, $exists);
        }

        $this->fireModelEvent('updatedHistory', false);

        return $this;
    }

    /**
     * Update the pivot record, if it exists, with the pivot attributes.
     *
     * @param  Carbon  $updatedAt
     * @param  ?Carbon $touchWithTimestamp
     * @param  ?array  &$historyLog
     * @param  ?bool   $exists
     * 
     * @return void
     */
    public function updatePivotHistoricRecord(Carbon $updatedAt, ?Carbon $deletedAt = null, ?array &$historyLog = [], ?bool $exists = false): void
    {
        if (isset($this->pivot) && $this->pivot) {
            // If the pivot has its own historic saving method, delegate to it.
            if (method_exists($this->pivot, 'updateAllHistory')) {
                $this->pivot->updateAllHistory($updatedAt, $deletedAt, $historyLog, $exists);
            }
        }
    }

    /**
     * Recursively process all loaded associations to save their historical records.
     *
     * @param  Carbon      $updatedAt
     * @param  ?Carbon     $deletedAt 
     * @param  ?array      &$historyLog
     * @param  bool|null   $exists
     * 
     * @return void
     */
    public function updateAssociationsHistoryRecords(Carbon $updatedAt, ?Carbon $deletedAt = null, ?array &$historyLog = [], ?bool $exists = false): void
    {
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

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @return bool|null
     */
    public function forceDelete()
    {
        if ($this->fireModelEvent('forceDeleting') === false) {
            return false;
        }

        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }
        });
    }

    /**
     * Force a hard delete on a soft deleted model without raising any events.
     *
     * @return bool|null
     */
    public function forceDeleteQuietly()
    {
        return static::withoutEvents(fn() => $this->forceDelete());
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param  \Illuminate\Support\Collection|array|int|string  $ids
     * @return int
     */
    public static function forceDestroy($ids)
    {
        if ($ids instanceof EloquentCollection) {
            $ids = $ids->modelKeys();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = is_array($ids) ? $ids : func_get_args();

        if (count($ids) === 0) {
            return 0;
        }

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = ($instance = new static)->getKeyName();

        $count = 0;

        foreach ($instance->withTrashed()->whereIn($key, $ids)->get() as $model) {
            if ($model->forceDelete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return mixed
     */
    protected function performDeleteOnModel()
    {
        if ($this->forceDeleting) {
            return tap($this->setKeysForSaveQuery($this->newModelQuery())->forceDelete(), function () {
                $this->exists = false;
            });
        }

        return $this->runSoftDelete();
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return void
     */
    protected function runSoftDelete(): void
    {
        $time = $this->freshTimestamp();
        $columns = [$this->getDeletedAtColumn() => $time];
        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps()) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $time;
        }

        $this->save();
        $this->syncOriginalAttributes(array_keys($columns));
        $this->fireModelEvent('trashed', false);
    }

    /**
     * Restore a soft-deleted model instance.
     *
     * @return bool
     */
    public function restore(): bool
    {
        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $id = $this->getKey();
        if (!$id) {
            return false;
        }

        $saved = false;
        $this->{$this->getDeletedAtColumn()} = static::$temporalConfig['max_timestamp'];
        $this->{$this->getUpdatedAtColumn()} = $this->freshTimestamp();
        $currentActive = $this->withoutTrashed()->find($id);
        if ($currentActive) {
            $currentActive->update($this->attributes);
            $saved = true;
        } else {
            $this->exists = true;
            $saved = $this->save();
        }

        $this->fireModelEvent('restored', false);
        return $saved;
    }

    /**
     * Restore a soft-deleted model instance without raising any events.
     *
     * @return bool
     */
    public function restoreQuietly()
    {
        return static::withoutEvents(fn() => $this->restore());
    }

    /**
     * Determine if the model instance has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        $endTime = Carbon::createFromTimestampMs(static::$temporalConfig['max_timestamp']);
        if ($this->{static::$temporalConfig['end_column']} < $endTime) {
            return true;
        }
        return false;
    }

    /**
     * Register a "softDeleted" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function softDeleted($callback)
    {
        static::registerModelEvent('trashed', $callback);
    }

    /**
     * Register a "restoring" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function restoring($callback)
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a "restored" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function restored($callback)
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Register a "forceDeleting" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function forceDeleting($callback)
    {
        static::registerModelEvent('forceDeleting', $callback);
    }

    /**
     * Register a "forceDeleted" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function forceDeleted($callback)
    {
        static::registerModelEvent('forceDeleted', $callback);
    }

    /**
     * Register a "updatingHistory" model event callback with the dispatcher.
     *
     * @param  \Illuminate\Events\QueuedClosure|callable|class-string  $callback
     * @return void
     */
    public static function updatingHistory($callback)
    {
        static::registerModelEvent('updatingHistory', $callback);
    }

    /**
     * Determine if the model is currently force deleting.
     *
     * @return bool
     */
    public function isForceDeleting()
    {
        return $this->forceDeleting;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        $constantName = static::class . '::DELETED_AT';
        return defined($constantName) ? constant($constantName) : static::$temporalConfig['end_column'];
    }

    /**
     * Returns the start column value
     * 
     * @return ($inMiliseconds is true ? ?int : ?Carbon)
     */
    public function getDeletedAt($inMiliseconds = false): null|int|Carbon
    {
        if ($this->{$this->getDeletedAtColumn()}) {
            $columnValue = $this->{$this->getDeletedAtColumn()};
            return $inMiliseconds ? $columnValue->getTimestampMs() : $columnValue;
        }
        return null;
    }
}
