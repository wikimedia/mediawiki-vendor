# DataValues Serialization

Serializers and deserializers for DataValue implementations.

It is part of the [DataValues set of libraries](https://github.com/DataValues).

[![Build Status](https://secure.travis-ci.org/DataValues/Serialization.png?branch=master)](http://travis-ci.org/DataValues/Serialization)
[![Code Coverage](https://scrutinizer-ci.com/g/DataValues/Serialization/badges/coverage.png?s=3e52443ffbf18b98804feb7c02ba4416f3f986cb)](https://scrutinizer-ci.com/g/DataValues/Serialization/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/DataValues/Serialization/badges/quality-score.png?s=818787ea88853bbfb76dc226eb4ab755729141c5)](https://scrutinizer-ci.com/g/DataValues/Serialization/)
[![Dependency Status](https://www.versioneye.com/php/data-values:serialization/badge.png)](https://www.versioneye.com/php/data-values:serialization)

On [Packagist](https://packagist.org/packages/data-values/serialization):
[![Latest Stable Version](https://poser.pugx.org/data-values/serialization/version.png)](https://packagist.org/packages/data-values/serialization)
[![Download count](https://poser.pugx.org/data-values/serialization/d/total.png)](https://packagist.org/packages/data-values/serialization)

## Installation

The recommended way to use this library is via [Composer](http://getcomposer.org/).

### Composer

To add this package as a local, per-project dependency to your project, simply add a
dependency on `data-values/serialization` to your project's `composer.json` file.
Here is a minimal example of a `composer.json` file that just defines a dependency on
version 1.0 of this package:

    {
        "require": {
            "data-values/serialization": "1.0.*"
        }
    }

### Manual

Get the code of this package, either via git, or some other means. Also get all dependencies.
You can find a list of the dependencies in the "require" section of the composer.json file.
Then take care of autoloading the classes defined in the src directory.

## Tests

This library comes with a set up PHPUnit tests that cover all non-trivial code. You can run these
tests using the PHPUnit configuration file found in the root directory. The tests can also be run
via TravisCI, as a TravisCI configuration file is also provided in the root directory.

## Authors

DataValues Serialization has been written by [Jeroen De Dauw](https://github.com/JeroenDeDauw),
as [Wikimedia Germany](https://wikimedia.de) employee for the [Wikidata project](https://wikidata.org/).

## Release notes

### 1.2.1 (2017-06-26)

* Fixed `DataValueDeserializer` not always turning internal `InvalidArgumentException` into
  `DeserializationException`, as documented.
* Raised required PHP version from 5.3 to 5.5.9.

### 1.2.0 (2017-01-31)

* Improved error reporting in the `DataValueDeserializer` constructor.
* Added a basic PHPCS rule set, can be run with `composer phpcs`.

### 1.1.0 (2016-05-24)

* Added support for builder functions to `DataValueDeserializer`

### 1.0.3 (2015-08-05)

* Removed duplicate catch clause
* Removed `composer update` from the PHPUnit bootstrap file

### 1.0.2 (2014-10-10)

* Made component installable together with DataValues 1.x

### 1.0.1 (2014-09-09)

* Handle IllegalValueException in DataValueDeserializer

### 1.0.0 (2014-03-05)

* Switched usage of the Serialization component from version ~2.1 to version ~3.0.
* Switched from PSR-0 based autoloading to PSR-4 based autoloading
* Made PHPUnit bootstrap file compatible with Windows

### 0.1.0 (2013-12-05)

Initial release with these features:

* DataValues\Serializers\DataValueSerializer - Adapter that fits the toArray method of DataValue
objects to the Serializer interface. This allows users to move to using the Serializer interface
and remove their exposure to how serialization of DataValues is implemented.
* DataValues\Deserializers\DataValueDeserializer - Adapter that fits the newFromArray method of
DataValues objects to the Deserializer interface. This allows users to remove the knowledge they
have of how deserialization is implemented and break their dependency on DataValueFactory.

## Links

* [DataValues Serialization on Packagist](https://packagist.org/packages/data-values/serialization)
* [DataValues Serialization on TravisCI](https://travis-ci.org/DataValues/Serialization)
* [DataValues Serialization on ScrutinizerCI](https://scrutinizer-ci.com/g/DataValues/Serialization/)
