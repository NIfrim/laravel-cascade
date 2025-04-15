<?php

namespace Nifrim\LaravelCascade\Tests\Database\Migrations;

use Nifrim\LaravelCascade\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'dummy_validate_other';

    public function up()
    {
        if (!$this->tableExists()) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger('id');

                // add fields
                $table->string('name')->nullable(false);

                // add indexes
                $table->primary(['id'], "{$this->table}_primary");
            });
        }
    }
};
