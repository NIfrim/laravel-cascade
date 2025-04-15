<?php

namespace Nifrim\LaravelCascade\Database;

use Illuminate\Database\Migrations\Migration as EloquentMigration;
use Illuminate\Support\Facades\Schema;

class Migration extends EloquentMigration
{
    protected string $table;

    /**
     * Method returns true if table exists in schema
     * 
     * @return bool
     */
    public function tableExists(): bool
    {
        return in_array(
            $this->table,
            array_column(Schema::getTables(), 'name')
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->tableExists()) {
            Schema::drop($this->table);
        }
    }
}
