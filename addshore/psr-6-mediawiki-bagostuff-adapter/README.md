# PSR-6 MediaWiki BagOStuff Adapter

This library provides a PSR-6 cache pool adapter for MediaWiki's `BagOStuff` cache backend.

## Requirements

- PHP `^8.2`
- `psr/cache:^3.0`

## Versions

- 0.1: Initial version, compatible with PSR/cache ^1.0.0
- 0.2: Updated to be compatible with PSR/cache ^3.0

## Installation

Install with Composer:

composer require addshore/psr-6-mediawiki-bagostuff-adapter

## Usage

Wrap a MediaWiki `BagOStuff` instance with the PSR-6 pool:

```php
use Addshore\Psr\Cache\MWBagOStuffAdapter\BagOStuffPsrCache;
use Wikimedia\ObjectCache\BagOStuff;

/** @var BagOStuff $bagOStuff */
$pool = new BagOStuffPsrCache( $bagOStuff );

$item = $pool->getItem( 'example-key' );
if ( !$item->isHit() ) {
    $item->set( 'value' );
    $pool->save( $item );
}
```

## Development

Install dependencies:

composer install

Run checks:

- `composer lint` — syntax checks via `parallel-lint`
- `composer sniff` — coding standards via `phpcs`
- `composer phpunit` — unit tests
- `composer test` / `composer ci` — run all checks

## Notes

- The historic `cache/integration-tests` suite is not currently suitable for PSR/cache 3 in this project context.
- This repository therefore uses local PHPUnit tests under `tests/unit`.