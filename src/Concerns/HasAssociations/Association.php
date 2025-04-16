<?php

namespace Nifrim\LaravelCascade\Concerns\HasAssociations;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Nifrim\LaravelCascade\Database\Relations\HasMany;
use Nifrim\LaravelCascade\Database\Relations\HasManyThrough;
use Nifrim\LaravelCascade\Database\Relations\HasOne;
use Nifrim\LaravelCascade\Database\Relations\HasOneThrough;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

/**
 * @phpstan-type TRelationClass class-string<HasOne|HasOneThrough|HasMany|HasManyThrough|BelongsTo|BelongsToMany>
 * 
 * @phpstan-type TAssociationOptions array{
 *    name?: string,
 *    modelClass?: class-string<EloquentModel>,
 *    relationClass?: TRelationClass,
 *    foreignKey: string,
 *    primaryKey?: string,
 *    relatedPrimaryKey?: string,
 *    tableName?: string,
 *    onDelete?: string,
 *    onUpdate?: string,
 * }
 * 
 * @phpstan-type TAssociationData null|EloquentModel|Collection<array-key,EloquentModel>
 * 
 * @property string $name
 * @property ?Association $pivot
 * @property TAssociationData $data
 * @property bool $canDelete
 * @property TRelationClass $relationClass
 * @property TAssociationOptions $options
 * 
 * @method static $this belongsToOne(string $name, TAssociationOptions $options)
 * @method static $this belongsToMany(string $name, TAssociationOptions $options, TAssociationOptions $pivotOptions)
 * @method static $this hasOne(string $name, TAssociationOptions $options)
 * @method static $this hasMany(string $name, TAssociationOptions $options)
 * @method static $this hasOneThrough(string $name, TAssociationOptions $options, TAssociationOptions $pivotOptions)
 * @method static $this hasManyThrough(string $name, TAssociationOptions $options, TAssociationOptions $pivotOptions)
 */
class Association
{
    protected static $associations = [
        'belongsToOne'    => BelongsTo::class,
        'belongsToMany'   => BelongsToMany::class,
        'hasOne'          => HasOne::class,
        'hasMany'         => HasMany::class,
        'hasOneThrough'   => HasOneThrough::class,
        'hasManyThrough'  => HasManyThrough::class,
    ];

    protected string $name;

    /**
     * @var TRelationClass
     */
    protected string $relationClass;

    /**
     * @var TAssociationOptions
     */
    protected array $options;

    /**
     * @var TAssociationData
     */
    protected null|string|EloquentModel|Collection $data = null;

    /**
     * When flag is true, cascade will attempt delete on related associations.
     * 
     * @var bool
     */
    protected bool $canDelete = false;

    /**
     * @var ?Association
     */
    protected ?Association $pivot = null;

    // /**
    //  * @var Options
    //  */
    // protected Options $options;

    /**
     * Class constructor. Access via `forge` static method.
     * 
     * @param string $name
     * @param TRelationClass $relationClass
     * @param TAssociationOptions $options
     * @param ?TAssociationOptions $pivotOptions
     * 
     * @return Association
     */
    private function __construct(
        string $name,
        string $relationClass,
        array $options,
        ?array $pivotOptions = null,
    ) {
        $this->name = $name;
        $this->relationClass = $relationClass;
        $this->options = [
            ...$options,
            'onDelete' => isset($options['onDelete']) ? $options['onDelete'] : AssociationActionType::NO_ACTION,
            'onUpdate' => isset($options['onUpdate']) ? $options['onUpdate'] : AssociationActionType::NO_ACTION,
        ];
        if ($pivotOptions) {
            $this->pivot = static::forge(
                $this->getPivotName($pivotOptions),
                $pivotOptions['relationClass'],
                [
                    ...$pivotOptions,
                    'relatedPrimaryKey' => isset($pivotOptions['relatedPrimaryKey']) ? $pivotOptions['relatedPrimaryKey'] : 'id',
                ]
            );
        }
    }

    /**
     * Static forge method to create and return a Options instance.
     * This enables a fluent interface and provides default values for common cases.
     * Could do without as `__callStatic` is used, but useful for extending functionality.
     *
     * @param string $name
     * @param TRelationClass $relationClass
     * @param TAssociationOptions $options
     * 
     * @return Association
     */
    protected static function forge(
        string $name,
        string $relationClass,
        array $options,
        ?array $pivot = null,
    ): Association {
        return new static($name, $relationClass, $options, $pivot);
    }

    /**
     * Control initialization of associations via `__callStatic` and `static::$associations`.
     * 
     * @param string $method
     * @param array $arguments
     */
    public static function __callStatic($method, $arguments)
    {
        if (!isset(static::$associations[$method])) {
            throw new BadMethodCallException("Method {$method} does not exist.");
        }

        $associationName = isset($arguments[0]) ? $arguments[0] : null;
        $associationClass = static::$associations[$method];
        $associationOptions = isset($arguments[1]) ? $arguments[1] : null;
        $pivotOptions = isset($arguments[2]) ? $arguments[2] : null;
        return static::forge($associationName, $associationClass, $associationOptions, $pivotOptions);
    }

    /**
     * Getter for properties.
     */
    public function __get($property)
    {
        if (isset($this->{$property})) {
            return $this->{$property};
        }
        return null;
    }

    /**
     * Returns the pivot name
     * 
     * @param TAssociationOptions $options
     */
    public function getPivotName(array $options): string
    {
        return isset($options['name']) ? $options['name'] : "pivot";
    }

    /**
     * Method checks if an association allows cascade delete
     * 
     * @param string $on - The direction to check, either onUpdate or onDelete
     * 
     * @return string
     */
    public function getCascadeAction(string $on): ?string
    {
        if (in_array($this->options[$on], [AssociationActionType::CASCADE, AssociationActionType::SET_NULL])) {
            return $this->options[$on];
        }

        return null;
    }

    /**
     * Check if relation is singular.
     */
    public function isSingular(): bool
    {
        return in_array($this->relationClass, [HasOne::class, BelongsTo::class, HasOneThrough::class]);
    }

    /**
     *  @return string
     */
    public function getTableName(): string
    {
        return $this->options['tableName'];
    }

    /**
     *  @return string
     */
    public function getPrimaryKey(): string
    {
        $primaryKey = isset($this->options['primaryKey']) ? $this->options['primaryKey'] : 'id';
        return $primaryKey;
    }

    /**
     *  @return TAssociationOptions['relatedPrimaryKey']
     */
    public function getRelatedPrimaryKey(): string
    {
        return $this->options['relatedPrimaryKey'];
    }

    /**
     *  @return TAssociationOptions['foreignKey']
     */
    public function getForeignKey(): string
    {
        return $this->options['foreignKey'];
    }

    /**
     *  @return TAssociationOptions['modelClass']
     */
    public function getModelClass(): string
    {
        return $this->options['modelClass'];
    }

    /**
     * Method returns model instance with related pivot (or null).
     * If data has primary key and record exists, then it finds it and fills it with the data.
     * Otherwise just builds a new model instance with the data.
     * 
     * @param EloquentModel $parentInstance - The parent instance which instantiated the association.
     * @param ?array $data
     * 
     * @return array[EloquentModel,?EloquentModel]
     */
    public function getModelWithPivotFromData(EloquentModel $parentInstance, ?array $data = null): ?array
    {
        $modelClass = $this->getModelClass();
        if (!$modelClass || !$data) {
            return null;
        }

        // Get the keys for checking existing record.
        $primaryKeyName = $this->getPrimaryKey();
        $foreignKeyName = $this->getForeignKey();
        $primaryKeyValue = isset($data[$primaryKeyName]) ? $data[$primaryKeyName] : null;
        if (!$primaryKeyValue && isset($data[$foreignKeyName])) {
            $primaryKeyValue = $data[$foreignKeyName];
        }

        // Ensure data has updated at value
        $updatedAtAttribute = [];
        if ($parentInstance->getUpdatedAtColumn() && method_exists($parentInstance, 'getUpdatedAt')) {
            $updatedAtAttribute = [
                $parentInstance->getUpdatedAtColumn() => $parentInstance->getUpdatedAt(),
            ];
        }

        /** @var null|EloquentModel $existing */
        $existing = isset($data[$primaryKeyName])
            ? $parentInstance->{$this->name}()
            ->firstWhere($primaryKeyName, $primaryKeyValue)
            : null;

        $modelInstance = $existing ? $existing->fill([...$updatedAtAttribute, ...$data]) : new $modelClass([...$updatedAtAttribute, ...$data]);

        if ($this->pivot) {
            $pivotModelClass = $this->pivot->getModelClass();
            $pivotName = $this->pivot->name;
            $relatedForeignKey = $this->pivot->getForeignKey();
            $updatedAtAttribute = $parentInstance->getUpdatedAtColumn() ? [$parentInstance->getUpdatedAtColumn() => $parentInstance->getUpdatedAt()] : [];
            $pivotAttributes = isset($data[$pivotName]) ? $data[$pivotName] : [
                $foreignKeyName => $parentInstance->getKey(),
                $relatedForeignKey => $primaryKeyValue,
                ...$updatedAtAttribute,
            ];
            $pivotInstance = $modelInstance->pivot ? $modelInstance->pivot->fill([...$updatedAtAttribute, ...$pivotAttributes]) : new $pivotModelClass([...$updatedAtAttribute, ...$pivotAttributes]);

            // Get the keys for filling the attributes.
            $primaryKeyName = $this->pivot->getPrimaryKey();
            $foreignKeyName = $this->pivot->getForeignKey();
            $primaryKeyValue = isset($pivotAttributes[$primaryKeyName]) ? $data[$primaryKeyName] : null;
            if (!$primaryKeyValue && isset($data[$foreignKeyName])) {
                $primaryKeyValue = $pivotAttributes[$foreignKeyName];
            }

            // Make new pivot on parent instance.
            return [$modelInstance, $pivotInstance];
        }

        return [$modelInstance, null];
    }

    // /**
    //  * Method returns model pivot instance.
    //  * If data has primary key and record exists, then it finds it and fills it with the data.
    //  * Otherwise just builds a new model instance with the data.
    //  * 
    //  * @param EloquentModel $parentInstance - The parent instance which instantiated the association.
    //  * @param ?array $data
    //  * 
    //  * @return ?EloquentModel
    //  */
    // public function getPivotFromData(EloquentModel $parentInstance, ?array $data = null): null|EloquentModel
    // {
    //   if (!$this->pivot || !$data) {
    //     return null;
    //   }

    //   $modelClass = $this->pivot->getModelClass();
    //   $pivotName = $this->pivot->name;
    //   $attributes = isset($data[$pivotName]) ? $data[$pivotName] : null;
    //   if (!$modelClass || !$attributes) {
    //     return null;
    //   }

    //   // Get the keys for checking existing record.
    //   // Can be in either pivot attributes or related data.
    //   // This helps to avoid duplicating data in case of an update.
    //   $primaryKeyName = $this->pivot->getPrimaryKey();
    //   $foreignKeyName = $this->pivot->getForeignKey();
    //   $primaryKeyValue = isset($data[$primaryKeyName]) ? $data[$primaryKeyName] : null;
    //   if (!$primaryKeyValue && isset($attributes[$foreignKeyName])) {
    //     $primaryKeyValue = $attributes[$foreignKeyName];
    //   }

    //   /** @var null|EloquentModel $existing */
    //   $existing = $primaryKeyValue
    //     ? $parentInstance->{$this->name}()
    //         ->firstWhere($primaryKeyName, $primaryKeyValue)
    //     : null;

    //   if ($existing && isset($existing->pivot) && $existing->pivot) {
    //     $existing = $existing->pivot->fill($attributes);
    //   }

    //   return $existing ?: new $modelClass($attributes);
    // }

    /**
     * Method sets the association and association pivot data.
     * The relation is set based on the returned association data.
     * 
     * @param EloquentModel $parentInstance - The parent instance which instantiated the association.
     * @param ?array $data
     * @param ?bool $canDeleteIfNoData
     * 
     * @return TAssociationData
     */
    public function setData(EloquentModel $modelInstance, ?array $data, ?bool $canDeletedIfNoData = false): mixed
    {
        if (!$data) {
            $this->canDelete = $canDeletedIfNoData;
            $this->data = null;
            return $this;
        }

        if (Arr::isAssoc($data)) {
            [$model, $pivot] = $this->getModelWithPivotFromData($modelInstance, $data);
            if ($this->pivot) {
                $this->pivot->data = $pivot;
            }
            return $this->data = $model;
        }

        // We have array of data,
        // so we need to initialize the association and pivot data as collections,
        // as the indexes are used to match the data to their related pivots, even if null.
        $this->data = new Collection([]);
        $this->pivot->data = new Collection([]);
        foreach ($data as $attributes) {
            [$model, $pivot] = $this->getModelWithPivotFromData($modelInstance, $attributes);
            $this->pivot->data->add($pivot);
            $this->data->add($model);
        }

        return $this->data;
    }

    /**
     * Method sets the association resolver using the defined association details.
     */
    public function setResolver(EloquentModel $modelInstance): void
    {
        $associationName   = $this->name;
        $associationType   = $this->relationClass;
        $relatedModelClass = $this->getModelClass();
        $foreignKey        = $this->getForeignKey();
        $pivot             = $this->pivot;
        $pivotThroughModel = $pivot ? $pivot->getModelClass() : null;
        $relatedPrimaryKey = $pivot ? $pivot->getRelatedPrimaryKey() : null;
        $relatedForeignKey = $pivot ? $pivot->getForeignKey() : null;
        $pivotTableName    = $pivot ? $pivot->getTableName() : null;

        switch ($associationType) {
            case HasOne::class:
                $modelInstance->resolveRelationUsing($associationName, function (EloquentModel $model) use ($relatedModelClass, $foreignKey) {
                    return $model->hasOne($relatedModelClass, $foreignKey, $model->getKeyName());
                });
                break;

            case HasMany::class:
                $modelInstance->resolveRelationUsing($associationName, function (EloquentModel $model) use ($relatedModelClass, $foreignKey) {
                    return $model->hasMany($relatedModelClass, $foreignKey, $model->getKeyName());
                });
                break;

            case HasOneThrough::class:
                if (!$pivot) {
                    throw new \Error("Missing pivot details for association <{$associationType}:{$associationName}>.");
                }

                $modelInstance->resolveRelationUsing($associationName, function (EloquentModel $model) use ($relatedModelClass, $relatedPrimaryKey, $pivotThroughModel, $foreignKey, $relatedForeignKey) {
                    return $model->hasOneThrough(
                        $relatedModelClass,
                        $pivotThroughModel,
                        $foreignKey,
                        $relatedPrimaryKey,
                        $model->getKeyName(),
                        $relatedForeignKey,
                    );
                });
                break;

            case HasManyThrough::class:
                if (!$pivot) {
                    throw new \Error("Missing pivot details for association <{$this->relationClass}:{$this->name}>.");
                }
                $modelInstance->resolveRelationUsing($this->name, function (EloquentModel $model) use ($relatedModelClass, $relatedPrimaryKey, $pivotThroughModel, $foreignKey, $relatedForeignKey) {
                    return $model->hasManyThrough(
                        $relatedModelClass,
                        $pivotThroughModel,
                        $foreignKey,
                        $relatedPrimaryKey,
                        $model->getKeyName(),
                        $relatedForeignKey,
                    );
                });
                break;

            case BelongsTo::class:
                $modelInstance->resolveRelationUsing($associationName, function (EloquentModel $model) use ($relatedModelClass, $foreignKey, $associationName) {
                    return $model->belongsTo($relatedModelClass, $foreignKey, $model->getKeyName(), $associationName);
                });
                break;

            case BelongsToMany::class:
                if (!$pivot) {
                    throw new \Error("Missing pivot details for association <{$associationType}:{$associationName}>.");
                }
                $modelInstance->resolveRelationUsing($associationName, function (EloquentModel $model) use ($relatedModelClass, $pivotTableName, $pivotThroughModel, $foreignKey, $relatedForeignKey, $relatedPrimaryKey, $associationName) {
                    $modelInstance = new $pivotThroughModel;
                    $resolver = $model->belongsToMany(
                        $relatedModelClass,
                        $pivotTableName,
                        $foreignKey,
                        $relatedForeignKey,
                        $model->getKeyName(),
                        $relatedPrimaryKey,
                        $associationName,
                    );

                    if ($model->usesTimestamps()) {
                        $resolver->withTimestamps();
                    }

                    $fillableAttributes = method_exists($modelInstance, 'getFillable') ? $modelInstance->getFillable() : [];
                    if ($fillableAttributes) {
                        $resolver->withPivot(...$fillableAttributes);
                    }

                    return $resolver->using($pivotThroughModel);
                });
                break;

            default:
                throw new \Error("Unhandled association <{$associationType}:{$associationName}>.");
        }
    }

    /**
     * Method sets the array with the associations that should be touched on save.
     * Handles `HasOne` and `HasMany` associations.
     * 
     * @return void
     */
    public function setTouches(EloquentModel $modelInstance): void
    {
        $touchedRelations = $modelInstance->getTouchedRelations();
        if (in_array($this->relationClass, [HasOne::class, HasMany::class, BelongsTo::class, BelongsToMany::class])) {
            if (!in_array($this->name, $touchedRelations)) $touchedRelations[] = $this->name;
        }
        $modelInstance->setTouchedRelations($touchedRelations);
    }
}
