<?php

namespace Nifrim\LaravelCascade\Tests\Database\Migrations;

use Nifrim\LaravelCascade\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $table = 'dummy_ticket';

    public function up()
    {
        if (!$this->tableExists()) {
            Schema::create($this->table, function (Blueprint $table) {
                // $table->unsignedInteger('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('flight_id');

                // add fields
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_to')->nullable();
                $table->timestamp('created_at')->nullable();

                // add indexes
                $table->primary(['flight_id', 'user_id', 'valid_from', 'valid_to'], "{$this->table}_primary");
            });
        }
    }
};
