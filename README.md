# A Laravel package that extends Eloquent's model functionality to support compound primary keys and cascading save/delete operations on associated models. This package enables easy specification of relationships while ensuring data integrity by automatically managing related models when saving or deleting parent records.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nifrim/laravel-cascade.svg?style=flat-square)](https://packagist.org/packages/nifrim/laravel-cascade)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nifrim/laravel-cascade/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nifrim/laravel-cascade/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nifrim/laravel-cascade/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nifrim/laravel-cascade/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nifrim/laravel-cascade.svg?style=flat-square)](https://packagist.org/packages/nifrim/laravel-cascade)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-cascade.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-cascade)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require nifrim/laravel-cascade
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="associations-cascade-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

This package adds two key features to your models via extensible models:
- **Historical Tracking (AsHistoric):**  
  Instead of simple soft deletes, changes to a model are archived in the same tables based on `valid_from` and `valid_to` attributes. These tables use `valid_from` and `valid_to` UNIX timestamp fields to define the time span during which a record was/is valid, the `valid_to` is set to `Nifrim\LaravelCascade\Constants\Model::END_OF_TIME` by default, setting this to anything less will consider the record as being **trashed**.
  
- **Cascading Associations (HasAssociations):**  
  The package allows you to define associations that automatically cascade create, update, and unlink operations. You can pass nested data for related models when creating or updating a parent model, and the package will handle the associated records accordingly.

Models extending `Nifrim\LaravelCascade\Models\Base` or `Nifrim\LaravelCascade\Models\BasePivot` will have the **Cascading Associations** implementation
Models extending `Nifrim\LaravelCascade\Models\Temporal` or `Nifrim\LaravelCascade\Models\TemporalPivot` will have both the **Cascading Associations** and the **Historical Tracking** implementations.

Below is an example showing how to define your models and use these features.

### 1. Define Your Models

#### DummyUser Model

Extend the `Nifrim\LaravelCascade\Models\Temporal` to enable historical tracking and cascading of associations. In this example, the `DummyUser` model might have associations such as a profile and flights.

```php
<?php

namespace App\Models;

use Nifrim\LaravelCascade\Constants\AssociationActionType;
use Illuminate\Database\Eloquent\Model;
use Nifrim\LaravelCascade\Models\Temporal;

class DummyUser extends Temporal
{
    protected $table = 'dummy_user';

    protected $fillable = ['code', 'name'];

    /**
     * @inheritDoc
     */
    public function setAssociations(array $associations = []): static
    {
        return parent::setAssociations([
            // A User has one Profile.
            Association::hasOne('profile', [
                'modelClass' => DummyUserProfile::class,
                'foreignKey' => 'user_id'
            ]),

            // A User has many Flights through a pivot `dummy_ticket` table.
            Association::belongsToMany('flights', [
                'modelClass'  => DummyFlight::class,
                'foreignKey'  => 'user_id',
            ], [
                'name'          => 'pivot',
                'tableName'     => 'dummy_ticket',
                'modelClass'    => DummyTicket::class,
                'relationClass' => BelongsTo::class,
                'foreignKey'    => 'flight_id',
                'onDelete'      => AssociationActionType::CASCADE,
            ]),
        ]);
    }
}
```

### 2. Create and Update Records with Cascading Associations

The package lets you pass nested relationship data when creating or updating a model. The associated records will be automatically created, updated, or unlinked, and historical revisions will be tracked.

**Creating a User with Associated Flights**

```php
use App\Models\DummyUser;

$data = [
    'code' => 'user_001',
    'name' => 'John Doe',
    'flights' => [
        [
            'title' => 'Flight 101',
            'destination_id' => 1, // Assuming destination with id 1 exists.
        ],
        [
            'title' => 'Flight 202',
            'destination_id' => 2, // Assuming destination with id 2 exists.
        ],
    ],
];

// This will create the user and the associated flights in one operation.
$user = DummyUser::create($data);
```

**Updating a User and Its Associations**

You can update both the parent model and its related models with a single call. For example, updating the user's name and one of their flights:

```php
use App\Models\DummyUser;

// Retrieve an existing user.
$user = DummyUser::find(1);

// Update the user’s name and the title of the first associated flight.
$firstFlight = $user->flights->first();
$user->update([
    'name' => 'Johnathan Doe',
    'flights' => [
        [
            'id'    => $firstFlight->id,
            'title' => 'Flight 101 - Updated',
        ],
    ],
]);
```

**Unlinking Associations**

To unlink related models, pass an empty array for that association:

```php
// Retrieve an existing user.
$user = DummyUser::find(1);

// This call unlinks all flights from the user.
$user->update(['flights' => []]);
```

### 3. Retrieving Historical Revisions

Since the package archives old data in historic tables, you can query past revisions. For example, to retrieve historical versions of a user:

```php
use App\Models\DummyUser;

// Retrieve an existing user.
$user = DummyUser::find(1);

// Retrieve historical revisions for a given user.
$historicRecords = DummyUser::onlyTrashed()
    ->with('flights')
    ->where('id', $user->id)
    ->get();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Nicolae Ifrim](https://github.com/nifrim)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
