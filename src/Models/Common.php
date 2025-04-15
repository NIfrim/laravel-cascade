<?php

namespace Nifrim\LaravelCascade\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Common
{
    /**
     * Method performs a check on both the declaring model and the matching result model
     * to check if the temporal attribute exists on both.
     * 
     * @param array<EloquentModel> $models
     * @param EloquentCollection $results
     */
    public static function canUseTemporalAttributes(array $models,  $results): bool
    {
        // Determine if both sides support a temporal attribute.
        $useTemporal = false;
        if (!empty($models) && $results->count()) {
            $firstModel = reset($models);
            $firstResult = $results->first();
            if (method_exists($firstModel, 'getUpdatedAt') && method_exists($firstResult, 'getUpdatedAt')) {
                $useTemporal = true;
            }
        }
        return $useTemporal;
    }
}
