<?php

namespace Nifrim\LaravelCascade\Tests\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nifrim\LaravelCascade\Database\Migration;

return new class extends Migration
{
    protected string $table = 'dummy_destination';

    public function up()
    {
        if (!$this->tableExists()) {
            Schema::create($this->table, function (Blueprint $table) {
                $table->unsignedBigInteger('id');

                // add fields
                $table->string('title')->nullable(false);
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_to')->nullable();
                $table->timestamp('created_at')->nullable();

                // add indexes
                $table->primary(['id', 'valid_from', 'valid_to'], "{$this->table}_primary");
            });
        }
    }
};
