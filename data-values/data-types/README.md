# DataTypes

PHP library defining the `DataTypes\DataType` class of which instances represent a type of value,
such as "positive integer" or "percentage".

[![Build Status](https://secure.travis-ci.org/wmde/DataTypes.png?branch=master)](http://travis-ci.org/wmde/DataTypes)
[![Code Coverage](https://scrutinizer-ci.com/g/wmde/DataTypes/badges/coverage.png?s=81ca9034e898d0ff2ee603ffdcf07835c9b5f0d3)](https://scrutinizer-ci.com/g/wmde/DataTypes/)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/wmde/DataTypes/badges/quality-score.png?s=2405ce60c089e7454598ae50e235f001b68bd5cb)](https://scrutinizer-ci.com/g/wmde/DataTypes/)
[![Dependency Status](https://www.versioneye.com/php/data-values:data-types/dev-master/badge.png)](https://www.versioneye.com/php/data-values:data-types/dev-master)

On [Packagist](https://packagist.org/packages/data-values/data-types):
[![Latest Stable Version](https://poser.pugx.org/data-values/data-types/version.png)](https://packagist.org/packages/data-values/data-types)
[![Download count](https://poser.pugx.org/data-values/data-types/d/total.png)](https://packagist.org/packages/data-values/data-types)

## Installation

You can use [Composer](http://getcomposer.org/) to download and install
this package as well as its dependencies. Alternatively you can simply clone
the git repository and take care of loading yourself.

### Composer

To add this package as a local, per-project dependency to your project, simply add a
dependency on `data-values/data-types` to your project's `composer.json` file.
Here is a minimal example of a `composer.json` file that just defines a dependency on
DataTypes 1.0:

    {
        "require": {
            "data-values/data-types": "~1.0"
        }
    }

### Manual

Get the DataTypes code, either via git, or some other means. Also get all dependencies.
You can find a list of the dependencies in the "require" section of the composer.json file.
Load all dependencies and the load the DataTypes library by including its entry point:
DataTypes.php.

## Tests

This library comes with a set up PHPUnit tests that cover all non-trivial code. You can run these
tests using the PHPUnit configuration file found in the root directory. The tests can also be run
via TravisCI, as a TravisCI configuration file is also provided in the root directory.

## Authors

DataTypes has been written by the Wikidata team at [Wikimedia Germany](https://wikimedia.de)
for the [Wikidata project](https://wikidata.org/).

## Release notes

### 2.0.0 (2017-11-14)
* Removed `DataTypesModules`
* Removed MediaWiki integration. The library is no longer a MediaWiki extension.
* Removed JavaScript files and internationalizations (moved to a separate package).

### 1.0.0 (2016-12-29)
* `DataType` and `DataTypeFactory` do not accept empty strings any more.
* Removed `DataType::getLabel` along with the `DataTypes\Message` class.
* Added `DataType::getMessageKey`.
* Added a basic PHPCS rule set, can be run with `composer phpcs`.

### 0.5.2 (2016-02-17)
* Fixed cache invalidation in `DataTypesModule`.
* Fixed `DataTypeFactory` to report invalid arguments on construction.

### 0.5.1 (2015-10-20)
* `DataTypeFactory::getTypes()` now returns array with typeId keys as documented

### 0.5.0 (2015-08-10)

#### Breaking changes
* `DataType` no longer takes an array of `ValueValidator` in its constructor
* `DataType::getValidators` has been removed
* `DataTypeFactory` now takes a map from data type id to data value type.
* `DataTypeFactory::registerBuilder` has been removed

### 0.4.3 (2015-06-18)

* Fixed version number constant.

### 0.4.2 (2015-06-18)

* Updated code documentation for being able to automatically generate a proper documentation using JSDuck.
* Removed the ResourceLoader module "dependencies" which had been defined by accident.

### 0.4.1 (2014-11-18)

* Improved path detection so it does not break when the library is included in `vendor` rather than `extensions`

### 0.4.0 (2014-05-21)

* Removed the global variable `wgDataTypes`

### 0.3.0 (2014-05-21)

* Rename `monolingual-text` to `monolingualtext`
* Rename `multilingual-text` to `multilingualtext`

### 0.2.1 (2014-05-06)

* Migrated the i18n support to the new MediaWiki JSON format
* The tests are now run on PHP 5.6 and HHVM on travis

### 0.2.0 (2014-03-14)

#### Breaking changes

* `dataTypes.DataType` JavaScript object may no longer be initialized from a `dataValues.DataValue` object.
* Removed `dataTypes.DataType.getLabel`.
* Removed global DataType registration in the `dataTypes` object; `DataTypeStore` is to be used instead.
* Split up generic "dataTypes" ResourceLoader module into "dataTypes.DataType" and "dataTypes.DataTypeStore".

#### Enhancements

* Removed MediaWiki and DataValues dependencies from JavaScript code.
* Made code PSR-4 compliant
* Removed ResourceLoader dependency of QUnit tests.
* Implemented DataTypeStore.

### 0.1.1 (2013-12-23)

* Remove assumption about where the extension is installed in the resource loading paths.

### 0.1.0 (2013-12-15)

Initial release.

## Links

* [DataTypes on Packagist](https://packagist.org/packages/data-values/data-types)
* [DataTypes on Ohloh](https://www.ohloh.net/p/DataTypesPHP)
* [TravisCI build status](https://travis-ci.org/wmde/DataTypes)
* [DataTypes on ScrutinizerCI](https://scrutinizer-ci.com/g/wmde/DataTypes/)
* [Issue tracker](https://phabricator.wikimedia.org/project/view/123/)
