<?php

namespace Nifrim\LaravelCascade\Database\Casts;

use Nifrim\LaravelCascade\Exceptions\InvalidArgumentException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

class UnixTimestamp
{

    /**
     * Method used to cast data when getting attribute from model.
     */
    public function get($model, string $key, $value, array $attributes): ?Carbon
    {
        return $this->parseAsDateInstance($value);
    }

    /**
     * Method used to cast data when setting attribute on model.
     */
    public function set($model, string $key, $value, array $attributes)
    {
        return $this->parseAsTimestamp($value);
    }

    /**
     * Returns parsed value as timestamp (integer)
     * 
     * @return int
     * @throws InvalidArgumentException
     */
    protected function parseAsTimestamp(mixed $value): ?int
    {
        if ($value instanceof Carbon || $value instanceof Date || !$value) {
            return $value ? $value->getTimestampMs() : null;
        }
        if (is_numeric($value)) {
            return $this->convertToMiliseconds($value);
        }
        if (is_string($value)) {
            return Carbon::createFromTimeString($value)->getTimestampMs();
        }

        throw new InvalidArgumentException(gettype($value), [Carbon::class, 'numeric', 'string']);
    }

    /**
     * Returns parsed value as a Carbon date instance.
     * 
     * @return Carbon
     * @throws InvalidArgumentException
     */
    protected function parseAsDateInstance(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon || !$value) {
            return $value;
        }
        if (is_numeric($value)) {
            // Convert seconds to milliseconds
            $value = $this->convertToMiliseconds($value);
            return Carbon::createFromTimestampMs($value);
        }
        if (is_string($value)) {
            try {
                return Carbon::createFromTimeString($value);
            } catch (\Throwable $th) {
                return Carbon::createFromDateString($value);
            }
        }

        throw new InvalidArgumentException(gettype($value), [Carbon::class, 'numeric', 'string']);
    }

    /**
     * Method used to convert numeric value to miliseconds.
     * If non numeric value is provided, it simply returns it.
     */
    protected function convertToMiliseconds(mixed $value): int
    {
        if ($value && is_numeric($value) && $value < 1e12) {
            return $value *= 1000;
        }
        return $value;
    }

    /**
     * Returns parsed value as either timestamp or carbon instance.
     * 
     * @param null|int|string|Carbon $value
     * @param string $parseAs - Either 'timestamp' | 'date'
     * 
     * @return ($parseAs is timestamp ? ?int : ?Carbon)
     * @throws InvalidArgumentException
     */
    public static function parseValue(mixed $value, string $parseAs = 'timestamp'): mixed
    {
        if ($parseAs === 'timestamp') {
            return (new static)->parseAsTimestamp($value);
        }

        if ($parseAs === 'date') {
            return (new static)->parseAsDateInstance($value);
        }

        throw new InvalidArgumentException($parseAs, ['timestamp', 'date']);
    }
}
