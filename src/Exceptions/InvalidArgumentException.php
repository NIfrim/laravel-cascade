<?php

namespace Nifrim\LaravelCascade\Exceptions;

use InvalidArgumentException as GlobalInvalidArgumentException;

class InvalidArgumentException extends GlobalInvalidArgumentException
{
    protected $message = 'Invalid argument type {received}. Expected <{expected}>.';

    protected $code = 'INV_ARG';

    /**
     * Construct the error object.
     * @link https://php.net/manual/en/error.construct.php
     * @param string $received The received unexpected type.
     * @param array $expected The expected types.
     */
    public function __construct(
        string $received,
        array $expected,
    ) {
        str_replace("{received}", $received, $this->message);
        str_replace("{expected}", join(', ', $expected), $this->message);
    }
}
