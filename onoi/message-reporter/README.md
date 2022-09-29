# Message Reporter

[![Build Status](https://secure.travis-ci.org/onoi/message-reporter.svg?branch=master)](http://travis-ci.org/onoi/message-reporter)
[![Code Coverage](https://scrutinizer-ci.com/g/onoi/message-reporter/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/onoi/message-reporter/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/onoi/message-reporter/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/onoi/message-reporter/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/onoi/message-reporter/version.png)](https://packagist.org/packages/onoi/message-reporter)
[![Packagist download count](https://poser.pugx.org/onoi/message-reporter/d/total.png)](https://packagist.org/packages/onoi/message-reporter)

An interface to report and relay arbitrary messages to registered handlers. This was part of
the [Semantic MediaWiki][smw] code base and is now being deployed as independent library.

## Requirements

PHP 5.6.99 or later

## Installation

The recommended installation method for this library is to add it as dependency to your [composer.json][composer].

```json
{
	"require": {
		"onoi/message-reporter": "~1.3"
	}
}
```

## Usage

The message reporter specifies `MessageReporter` and `MessageReporterAware` as an interface for all interactions with a set of supporting classes:
- `MessageReporterFactory`
- `ObservableMessageReporter`
- `NullMessageReporter`
- `SpyMessageReporter`
- `CallbackMessageReporter`

```php
use Onoi\MessageReporter\MessageReporterFactory;
use Onoi\MessageReporter\MessageReporterAware;
use Onoi\MessageReporter\MessageReporterAwareTrait;

class Bar implements MessageReporterAware {

	use MessageReporterAwareTrait;

	public function __construct() {
		$this->messageReporter = MessageReporterFactory::getInstance()->newNullMessageReporter();
	}

	public function doSomething() {
		$this->messageReporter->reportMessage( 'Doing ...' );
	}
}
```

```php
use Onoi\MessageReporter\MessageReporterFactory;
use Onoi\MessageReporter\MessageReporter;

class Foo implements MessageReporter {

	public function reportMessage( $message ) {
		// output
	}
}

$foo = new Foo();

$messageReporterFactory = new MessageReporterFactory();

$observableMessageReporter = $messageReporterFactory->newObservableMessageReporter();
$observableMessageReporter->registerReporterCallback( array( $foo, 'reportMessage' ) );

or

// If the class implements the MessageReporter
$observableMessageReporter->registerMessageReporter( $foo );

$bar = new Bar();
$bar->setMessageReporter( $observableMessageReporter );
```

## Contribution and support

If you want to contribute work to the project please subscribe to the
developers mailing list and have a look at the [contribution guidelinee](/CONTRIBUTING.md). A list of people who have made contributions in the past can be found [here][contributors].

* [File an issue](https://github.com/onoi/message-reporter/issues)
* [Submit a pull request](https://github.com/onoi/message-reporter/pulls)

## Development

Start by installing the project dependencies by executing

    composer update

You can run the tests by executing

    make test
    
You can run the style checks by executing

    make cs
    
To run all CI checks, execute

    make ci
    
You can also invoke PHPUnit directly to pass it arguments, as follows

    vendor/bin/phpunit --filter SomeClassNameOrFilter

## Release notes

* 1.4.2 (2021-01-15)
  - Added support for PHP 8
  - Changed minimum PHP version to 7.3

* 1.4.1 (2019-04-10)
  - Added `.gitattributes`

* 1.4.0 (2019-04-08)
  - Added `CallbackMessageReporter`
  - Changed minimum PHP version to 5.6.99

* 1.3.0 (2017-11-05)
  - Added `MessageReporterAwareTrait`

* 1.2.0 (2016-08-02)
  - Added `MessageReporterAware` and `SpyMessageReporter`

* 1.1.0 (2016-04-13)
  - `ObservableMessageReporter::registerReporterCallback` to register only callable handlers

* 1.0.0 (2015-01-24)
  - Initial release
  - `MessageReporterFactory`
  - `ObservableMessageReporter`
  - `NullMessageReporter`
  - `MessageReporter`

## License

[GNU General Public License 2.0 or later][license].

[composer]: https://getcomposer.org/
[contributors]: https://github.com/onoi/message-reporter/graphs/contributors
[license]: https://www.gnu.org/copyleft/gpl.html
[travis]: https://travis-ci.org/onoi/message-reporter
[smw]: https://github.com/SemanticMediaWiki/SemanticMediaWiki/
