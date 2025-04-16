## Installation

You can install the package via composer:

```bash
composer require nifrim/laravel-cascade
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-cascade-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-cascade-config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$laravelCascade = new Nifrim\LaravelCascade();
echo $laravelCascade->echoPhrase('Hello, Nifrim!');
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
