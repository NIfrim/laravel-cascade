<?php

namespace Nifrim\LaravelCascade\Config;

return [

  /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

  'default' => 'sqlite',

  /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

  'connections' => [

    'sqlite' => [
      'driver' => 'sqlite',
      'database' => './database/testing.sqlite',
      'prefix' => '',
      'foreign_key_constraints' => false,
      'busy_timeout' => null,
      'journal_mode' => null,
      'synchronous' => null,
    ],

  ],

  /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

  'migrations' => [
    'table' => 'migrations',
    'update_date_on_publish' => true,
  ],

];
