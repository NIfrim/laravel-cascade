<?php

namespace Nifrim\LaravelCascade\Models;

use Nifrim\LaravelCascade\Concerns\HasAssociations;
use Nifrim\LaravelCascade\Concerns\HasAttributes;
use Nifrim\LaravelCascade\Database\Relations\BelongsTo;
use Nifrim\LaravelCascade\Database\Relations\BelongsToMany;
use Nifrim\LaravelCascade\Database\Relations\HasMany;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Nifrim\LaravelCascade\Database\Relations\HasManyThrough;
use Nifrim\LaravelCascade\Database\Relations\HasOne;
use Nifrim\LaravelCascade\Database\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class Base extends EloquentModel
{
    use HasAttributes, HasAssociations;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * Method returns a string to serve as the cache key.
     * 
     * @param ?string $key
     * 
     * @return string
     * @throws LogicException
     */
    public function getCacheKey(?string $key = null): string
    {
        if ($this->getKey()) {
            // $pivotClass = $this->pivot ? $this->pivot::class : 'no_pivot';
            // return get_class($this) . "|" . ($key ?: $this->getKey()) . "|" . $pivotClass;
            return get_class($this) . "|" . ($key ?: $this->getKey());
        }
        throw new LogicException('Cannot get cache key for a model with no key, implement own getter for custom cases');
    }

    /**
     * Get the valuf of the "created at" column.
     */
    public function getCreatedAt()
    {
        return $this->{$this->getCreatedAtColumn()};
    }

    /**
     * Get the valuf of the "created at" column.
     * 
     * @return self
     */
    public function setCreatedAt($value): static
    {
        $this->{$this->getCreatedAtColumn()} = $value;
        return $this;
    }

    /**
     * Get the name of the "deleted at" column.
     *
     * @return string
     */
    public function getDeletedAtColumn()
    {
        $constantName = static::class . '::DELETED_AT';
        return defined($constantName) ? constant($constantName) : null;
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Get the "deleted at" value.
     */
    public function getDeletedAt()
    {
        return $this->{$this->getDeletedAtColumn()};
    }

    /**
     * Set the "deleted at" value.
     * 
     * @return self
     */
    public function setDeletedAt($value)
    {
        $this->{$this->getDeletedAtColumn()} = $value;
        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function newBelongsTo(Builder $query, EloquentModel $child, $foreignKey, $ownerKey, $relationName)
    {
        $resolver = new BelongsTo($query, $child, $foreignKey, $ownerKey, $relationName);
        return $resolver;
    }

    /**
     * @inheritDoc
     */
    protected function newBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        $resolver = new BelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
        return $resolver->withSoftDeletes();
    }

    /**
     * @inheritDoc
     */
    protected function newHasOne(Builder $query, EloquentModel $parent, $foreignKey, $localKey)
    {
        $resolver = new HasOne($query, $parent, $foreignKey, $localKey);
        return $resolver;
    }

    /**
     * @inheritDoc
     */
    protected function newHasMany(Builder $query, EloquentModel $parent, $foreignKey, $localKey)
    {
        $resolver = new HasMany($query, $parent, $foreignKey, $localKey);
        return $resolver;
    }

    /**
     * @inheritDoc
     */
    protected function newHasOneThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey)
    {
        $resolver = new HasOneThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
        return $resolver;
    }

    /**
     * @inheritDoc
     */
    protected function newHasManyThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey)
    {
        $resolver = new HasManyThrough($query, $farParent, $throughParent, $firstKey, $secondKey, $localKey, $secondLocalKey);
        return $resolver;
    }
}
