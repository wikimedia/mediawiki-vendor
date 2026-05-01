[![Latest Stable Version]](https://packagist.org/packages/wikimedia/json-codec) [![License]](https://packagist.org/packages/wikimedia/json-codec)

JsonCodec
=====================

Interfaces to serialize and deserialize PHP objects to/from JSON.

Additional documentation about this library can be found on
[mediawiki.org](https://www.mediawiki.org/wiki/JsonCodec).


Usage
-----

To make an object serializable/deserializable to/from JSON, the
simplest way is to use the `JsonCodecableTrait` and implement two
methods in your class, `toJsonArray()` and the static method
`newFromJsonArray()`:
```php
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

class SampleObject implements JsonCodecable {
	use JsonCodecableTrait;

	/** @var string */
	public string $property;

	// ....

	// Implement JsonCodecable using the JsonCodecableTrait

	/** @inheritDoc */
	public function toJsonArray(): array {
		return [
			'property' => $this->property,
		];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): SampleObject {
		return new SampleObject( $json['property'] );
	}
}
```
A slightly more complicated version of this example can be found in
[`tests/SampleObject.php`](./tests/SampleObject.php).

If your class requires explicit management -- for example, object
instances need to be created using a factory service, you can
implement `JsonCodecable` directly:
```php
use Psr\Container\ContainerInterface;
use Wikimedia\JsonCodec\JsonCodecable;

class ManagedObject implements JsonCodecable {
	public static function jsonClassCodec( ContainerInterface $serviceContainer ) {
		$factory = $serviceContainer->get( 'MyObjectFactory' );
		return new class( $factory ) implements JsonClassCodec {
			// ...
			public function toJsonArray( $obj ): array {
				// ...
			}
			public function newFromJsonArray( string $className, array $json ): ManagedObject {
				return $this->factory->create( $json[....] );
			}
		};
	}
}
```
A full example can be found in
[`tests/ManagedObject.php`](./tests/ManagedObject.php).

Note that array returned by `toJsonArray()` can include other
`JsonCodecable` objects, which will be recursively serialized.
When `newFromJsonArray` is called during deserialization, all
of these recursively included objects will already have been
deserialized back into objects.

To serialize an object to JSON, use [`JsonCodec`](./src/JsonCodec.php):
```php
use Wikimedia\JsonCodec\JsonCodec;

$services = ... your global services object, or null ...;
$codec = new JsonCodec( $services );

$string_result = $codec->toJsonString( $someComplexValue );
$someComplexValue = $codec->newFromJsonString( $string_result );
```

In some cases you want to embed this output into another context,
or to pretty-print the output using non-default `json_encode` options.
In these cases it can be useful to have access to methods which
return or accept the array form of the encoding, just before
json encoding/decoding:
```php
$array_result = $codec->toJsonArray( $someComplexValue );
var_export($array_result); // pretty-print
$request->jsonResponse( [ 'error': false, 'embedded': $array_result ] );

$someComplexValue = $codec->fromJsonArray( $data['embedded'] );
```

### Handling "non-codecable" objects

In some cases you want to be able to serialize/deserialize third-party
objects which don't implement JsonCodecable.  This can be done using
the JsonCodec method `::addCodecFor()` which allows the creator of
the `JsonCodec` instance to specify a `JsonClassCodec` to use for
an arbitrary class name.  For example:
```php
use Wikimedia\JsonCodec\JsonCodec;

$codec = new JsonCodec( ...optional services object... );
$codec->addCodecFor( \DocumentFragment::class, new MyDOMSerializer() );

$string_result = $codec->toJsonString( $someComplexValue );
```
This is done by default to provide a serializer for `stdClass` objects.

If adding class codecs one-by-one is not sufficient, for example if
you wish to add support for all objects implementing some
alternate serialization interface, you can subclass `JsonCodec` and
override the protected `JsonCodec::codecFor()` method to return
an appropriate codec.  Your code should look like this:
```php
use Wikimedia\JsonCodec\JsonCodec;
use Wikimedia\JsonCodec\JsonClassCodec;

class MyCustomJsonCodec extends JsonCodec {
   protected function codecFor( string $className ): ?JsonClassCodec {
      $codec = parent::codecFor( $className );
      if ($codec === null && is_a($className, MyOwnSerializationType::class, true)) {
         $codec = new MyCustomSerializer();
         // Cache this for future use
         $this->addCodecFor( $className, $codec );
      }
      return $codec;
  }
}
```
A full example can be found in
[`tests/AlternateCodec.php`](./tests/AlternateCodec.php).

### Enumerations

PHP 8.1 enum types are codecable by default.  `BackedEnum` types
serialize as `[ 'value' => ... ]` using their backing value, and
other enum types serialize as `[ 'name' => ... ]` using their case
name.  You can customize the serialization of the enum either by
having your `Enum` type implement `JsonCodecable`, or by
using the `::addCodecFor()` mechanism used for "non-codecable"
objects.  Either of these is used in preference to the default
[`JsonEnumClassCodec`](./src/JsonEnumClassCodec.php) which handles
enums.

### More concise output

By default JsonCodec embeds the class name of the appropriate object
type into the JSON output to enable reliable deserialization.  In some
applications, however, concise JSON output is desired.  By providing
an optional "class hint" to the top-level call to `::toJsonArray()` and
`newFromJsonArray()` and implementing the `::jsonClassHintFor()`
method in your class codec you can suppress unnecessary type
information in the JSON when your provided hint matches what would
have been added.  For example:

```
use Wikimedia\JsonCodec\Hint;
use Wikimedia\JsonCodec\HintType;
use Wikimedia\JsonCodec\JsonCodecable;
use Wikimedia\JsonCodec\JsonCodecableTrait;

class SampleContainerObject implements JsonCodecable {
	use JsonCodecableTrait;

	/** @var mixed */
	public $contents;
	/** @var list<Foo> */
	public array $foos;

	// ...

	// Implement JsonCodecable using the JsonCodecableTrait

	/** @inheritDoc */
	public function toJsonArray(): array {
		return [ 'contents' => $this->contents, 'foos' => $this->foos ];
	}

	/** @inheritDoc */
	public static function newFromJsonArray( array $json ): SampleContainerObject {
		return new SampleContainerObject( $json['contents'], $json['foos'] );
	}

	/** @inheritDoc */
	public static function jsonClassHintFor( string $keyName ) {
		if ( $keyName === 'contents' ) {
			// Hint that the contained value is a SampleObject. It might be!
			return SampleObject::class;
		} elseif ( $keyName === 'foos' ) {
			// A hint with a modifier
			return Hint::build( Foo::class, HintType::LIST );
		}
		return null;
	}
}
```

You can then generate concise output by providing the proper hints
when serializing and deserializing:
```
use Wikimedia\JsonCodec\JsonCodec;

$codec = new JsonCodec();

$value = new SampleContainerObject( new SampleObject( 'sample' ), ... );
$string_result = $codec->toJsonString( $value, SampleContainerObject::class );

// $string_result is now:
//    {"contents":{"property":"sample"},"foos":[...]}'
// with no explicit type information.

// But we need to provide the same class hint when deserializing:
$value = $codec->newFromJsonString( $string_result, SampleContainerObject::class );
```

Note that the provided value is a *hint*.  If we were to put a value
other than a `SampleObject` into the `SampleContainerObject` the type
of that value would be embedded into the JSON output, but it would not
break serialization/deserialization.

As illustrated with the `foos` property, to indicate a homogenous list
or array of the given type, you can pass `Hint::build(....,
HintType::LIST)` as the class hint.  A `stdClass` object where properties
are values of the given type can be hinted with `Hint::build(....,
HintType::STDCLASS)`.

A full example can be found in
[`tests/SampleContainerObject.php`](./tests/SampleContainerObject.php).

The `HintType::USE_SQUARE` modifier allows `::toJsonArray()` to
return a list (see
[`array_is_list`](https://www.php.net/manual/en/function.array-is-list.php))
and have that list encoded as a JSON array, with square `[]` brackets.

The `HintType::ALLOW_OBJECT` modifier ensures that empty objects are
serialized as `{}`.  It has the side effect that `::toJsonArray()` may
in some cases return an _object_ value instead of the _array_ value
implied from the method name.

The `USE_SQUARE` and `ALLOW_OBJECT` hints are necessary because
normally `JsonCodec` attempts to encode all _object values_ with curly `{}`
brackets by inserting a `_type_` property in the encoded result when
necessary to ensure that the encoded array is never a list.
PHP's `json_encode` will use `{}` notation for non-list arrays.  If you
don't want the added `_type_` property added to your encoded result,
then you need to specify whether you prefer `[]` notation (`USE_SQUARE`)
or `{}` notation (`ALLOW_OBJECT`) to be used in ambiguous cases.

An example with hint modifiers can be found in
[`tests/SampleList.php`](./tests/SampleList.php) and its associated
test cases.

Where a superclass codec can be used to instantiate objects of
various subclasses the `HintType::INHERITED` modifier can be used.
An example of this can be found in
[`tests/Pet.php`](./tests/Pet.php),
[`tests/Dog.php`](./tests/Dog.php), and
[`tests/Cat.php`](./tests/Cat.php)
and their associated test cases in
[`tests/JsonCodecTest.php`](./tests/JsonCodecTest.php).

For forward-compatibility with JSON serialized with hints, you
may specify `HintType::ONLY_FOR_DECODE` to indicate that serialization
should ignore the hint (encoding full class information) but that
deserialization should take the hint into account, so that it is
possible to read encodings produced either with or without a
future hint.

In some cases, `::jsonClassHintFor()` may be inadequate to describe
the implicit typing of the JSON; for example tagged union values or
implicitly-typed objects nested deeply or inside non-homogeneous
arrays.  For those use cases a `JsonCodecInterface` parameter is
provided to the `::jsonClassCodec()` method.  This allows the
serialization/deserialization code to manually encode/decode portions
of its JSON array using an implicit type.  More details can be found
in the interface documentation for
[`src/JsonCodecInterface.php`](./src/JsonCodecInterface.php) and a
full example can be found in
[`tests/TaggedValue.php`](./tests/TaggedValue.php).

Further customization of the encoding of class names and class hints
is available using the protected methods `JsonCodec::isArrayMarked()`,
`JsonCodec::markArray()` and `JsonCodec::unmarkArray()`.  A full
example can be found in
[`tests/ReservedKeyCodec.php`](./tests/ReservedKeyCodec.php).

### Type abbreviations
The hint mechanism allows encodings to omit explicit type information
in many cases, but generic container types may not be able to fully
hint their contents.  Further, for cross-platform use it is useful to
have a way to explicitly encode class types *without* using the
literal name of a PHP class.  The type abbreviation mechanism allows
the codec to define a standard set of abbreviations for PHP classes.
Without hinting, the encoded JSON output might look like:
```
{"_type_":"\\My\\PHP\\Namespace\\ClassName", ...}
```
Calling `JsonCodec::addAbbrev()` allows you to define an abbreviation:
```
$codec->addAbbrev( 'my-type-abbreviation', ClassName::class );
```
and subsequently you can encode/decode:
```
{"_type_":"@my-type-abbreviation",...}
```
A cross-platform encoder can recognize `@my-type-abbreviation` and
substitute an appropriate class implementation in a non-PHP language.

Although JsonCodec supports PHP's `class_alias`, abbreviations also
provide another way to allow the PHP implementation class to be
renamed or renamespaced without breaking decode.

For forward-compatibility, abbreviations added using
`HintType::ONLY_FOR_DECODE` won't be used in the encoded string:
```
$codec->addAbbrev(
  'my-type-abbreviation',
  Hint::build(ClassName::class, HintType::ONLY_FOR_DECODE)
);
```

Finally, the codec abbreviation registry provides a mechanism to
generically provide hints for encode/decode. For example, a JSON-valued
attribute mechanism for HTML might use the attribute name as the
default hint to allow a generic getter:
```
public function getJsonAttribute(Element $element, string $name) {
  $value = $element->getAttribute($name);
  $hint = $this->codec->getAbbrev("attr-$name");
  return $this->codec->newFromJsonString($value, $hint);
}
```
Abbreviations can be added to the codec using
`JsonCodec::addAbbrev()`, and registered abbreviations can be
retrieved from the codec using `JsonCodec::getAbbrev()` as shown above.

Running tests
-------------

```
composer install
composer test
```

History
-------
The JsonCodec concept was first introduced in MediaWiki 1.36.0 ([dbdc2a3cd33](https://gerrit.wikimedia.org/r/c/mediawiki/core/+/641575/)). It was
split out of the MediaWiki codebase and published as an independent library
during the MediaWiki 1.41 development cycle, with changes to the API.

---
[Latest Stable Version]: https://poser.pugx.org/wikimedia/json-codec/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/json-codec/license.svg
