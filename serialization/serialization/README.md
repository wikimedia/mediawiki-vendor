# Serialization

[![Build Status](https://github.com/wmde/Serialization/actions/workflows/lint-and-test.yaml/badge.svg?branch=master)](https://github.com/wmde/Serialization/actions/workflows/lint-and-test.yaml)
[![Code Coverage](https://scrutinizer-ci.com/g/wmde/Serialization/badges/coverage.png?s=c1db04f88f763f63dc0f0d8315cf9b8491fc81e6)](https://scrutinizer-ci.com/g/wmde/Serialization/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/wmde/Serialization/badges/quality-score.png?s=d25b9d7cbc4a737817ebf072d2e4b55b0bd8b662)](https://scrutinizer-ci.com/g/wmde/Serialization/)

On Packagist:
[![Latest Stable Version](https://poser.pugx.org/serialization/serialization/version.png)](https://packagist.org/packages/serialization/serialization)
[![Download count](https://poser.pugx.org/serialization/serialization/d/total.png)](https://packagist.org/packages/serialization/serialization)

Small library defining a Serializer and a Deserializer interface.

Also contains various Exceptions and a few basic (de)serialization utilities.

## Requirements

* PHP 7.4 or later

## Installation

You can use [Composer](http://getcomposer.org/) to download and install
this package as well as its dependencies. Alternatively you can simply clone
the git repository and take care of loading yourself.

### Composer

To add this package as a local, per-project dependency to your project, simply add a
dependency on `serialization/serialization` to your project's `composer.json` file.
Here is a minimal example of a `composer.json` file that just defines a dependency on
Serialization 4.x:

    {
        "require": {
            "serialization/serialization": "^4.0"
        }
    }

### Manual

Get the Serialization code, either via git, or some other means. Also get all dependencies.
You can find a list of the dependencies in the "require" section of the composer.json file.
This file also specifies how the resources provided by this library should be loaded, in
its "autoload" section.

## Usage

### Library structure

This component contains two sub parts, one containing serialization related code, the
other holding deserialization specific code. The former is located in the Serializers
namespace, while the latter resides in the Deserializers one. Both namespaces are PSR-0
mapped onto the src directory.

### Interfaces

The primary thing provided by this library are the Serializer and Deserializer namespaces.
A set of Exceptions each process typically can encounter are also provided, and are located
in respective Exceptions namespaces. They all derive from
`SerializationException`/`DeserializationException`.

The main interfaces define the `serialize`/`deserialize` methods that do the actual work.
In addition there are interfaces with a `isSerializerFor`/`isDeserializerFor` method
respectively that allows finding out if a given (de)serializer can process a given input.

### Utilities

A DispatchingSerializer and a DispatchingDeserializer are two generally usable implementations
of the interfaces that are included in this library. They both do the same thing: contain a
list of (de)serializers and dispatch calls to the (de)serialize method to the appropriate one.
This allows for bundling multiple (de)serializers together and enables handling of nested
data with variable structure.

## Tests

This library comes with a set up PHPUnit tests that cover all non-trivial code. You can run these
tests using the PHPUnit configuration file found in the root directory. The tests can also be run
via TravisCI, as a TravisCI configuration file is also provided in the root directory.

The library contains some code that was split factored out of concrete classes part of
[AskSerialization](https://github.com/wmde/AskSerialization). Those tests have not been
split, hence the low apparent coverage. It is recommended to run the AskSerialization
tests when making changes to the code in question.

## Authors

Serialization has been written by [Jeroen De Dauw](https://www.mediawiki.org/wiki/User:Jeroen_De_Dauw)
as [Wikimedia Germany](https://wikimedia.de) employee for the [Wikidata project](https://wikidata.org/).

## Release notes

### 5.0.0 (dev)

### 4.1.0 (2024-12-11)

* Drop support for PHP 7.2, 7.3
* Upgrade codesniffer rules to current `mediawiki/mediawiki-codesniffer` version (45.0.0)
* Make nullable type parameter declarations explicit for compatibility with PHP 8.4
* Type hinted the `$previous` parameter as `Throwable` instead of `Exception`
* Updated minimum required PHP version from `5.5.9` to `7.2` (HHVM is no longer supported)
* Updated to `GPL-2.0-or-later` according to SPDX v3
* Added a default message to the MissingTypeException

### 4.0.0 (2017-10-25)

* Removed the `Serialization_VERSION` constant.
* Removed underspecified `StrategicDeserializer` along with the abstract
  `TypedDeserializationStrategy` base class.
* Removed undocumented `TypedObjectDeserializer::requireAttributes`.
* Declared various protected properties and methods private:
	* `DispatchingDeserializer::$deserializers`
	* `DispatchingDeserializer::assertAreDeserializers`
	* `DispatchingSerializer::$serializers`
	* `DispatchingSerializer::assertAreSerializers`
	* `InvalidAttributeException::$attributeName`
	* `InvalidAttributeException::$attributeValue`
	* `MissingAttributeException::$attributeName`
	* `TypedObjectDeserializer::$objectType`
	* `UnsupportedObjectException::$unsupportedObject`
	* `UnsupportedTypeException::$unsupportedType`
* Deprecated pure utility functions on `TypedObjectDeserializer`:
	* `assertAttributeInternalType`
	* `assertAttributeIsArray`
	* `requireAttribute`
* Added default messages to `InvalidAttributeException`, `MissingAttributeException`, and
  `UnsupportedTypeException`.
* Added documentation to the `Serializer` and `Deserializer` interfaces.
* Updated minimal required PHP version from 5.3 to 5.5.9.

### 3.2.1 (2014-08-19)

* Tested against hhvm-nightly
* Tests now run in strict mode

### 3.2.0 (2014-05-20)

* Made SerializationException non-abstract

### 3.1.0 (2014-03-18)

* TypedObjectDeserializer now explicitly implements DispatchableDeserializer.

### 3.0.0 (2014-03-05)

* Split is(Des/S)erializerFor methods off into new Dispatchable(Des/S)erializer interfaces
* Changed from classmap based autoloading to PSR-4 based autoloading
* Improved PHPUnit bootstrap

### 2.2.0 (2013-12-11)

* Removed custom autoloader in favour of using the declarative system provided by Composer

### 2.1.0 (2013-11-19)

* The type key in TypedObjectDeserializer can now be specified via a constructor argument
* TypedObjectDeserializer now has some tests in this component itself
* The documentation was somewhat improved

### 2.0.0 (2013-09-05)

* Renamed Serializer::canSerialize to Serializer::isDeserializerFor
* Renamed Deserializer::canDeserialize to Deserializer::isDeserializerFor

### 1.0.0 (2013-07-13)

* Initial release.

## Links

* [Serialization on Packagist](https://packagist.org/packages/serialization/serialization)
* [Serialization on Ohloh](https://www.ohloh.net/p/serialization-php)
* [TravisCI build status](https://travis-ci.org/wmde/Serialization)
* [Serialization on ScrutinizerCI](https://scrutinizer-ci.com/g/wmde/Serialization/)
