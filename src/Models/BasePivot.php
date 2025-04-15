<?php

namespace Nifrim\LaravelCascade\Models;

use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

class BasePivot extends Base
{
    use AsPivot;

    protected $primaryKey = null;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];
}
