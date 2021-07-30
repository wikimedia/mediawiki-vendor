[![Latest Stable Version]](https://packagist.org/packages/wikimedia/dodo) [![License]](https://packagist.org/packages/wikimedia/dodo)

Dodo
=====================

Dodo is a port of [Domino.js](https://github.com/fgnass/domino) to
PHP, in order to provide a more performant and spec-compliant DOM
library than the DOMDocument PHP classes (`xml` extension), which is
built on [libxml2](www.xmlsoft.org).

Dodo uses a PHP binding for WebIDL defined by
[IDLeDOM](https://packagist.org/packages/wikimedia/idle-dom).
Details of the WebIDL binding can be found in the IDLeDOM documentation.

Additional documentation about the library can be found on
[MediaWiki.org](https://www.mediawiki.org/wiki/Dodo).

Report issues on [Phabricator](https://phabricator.wikimedia.org/maniphest/task/edit/form/1/?projects=Parsoid&title=Dodo:%20).

## Install
This package is [available on Packagist](https://packagist.org/packages/wikimedia/dodo):

```bash
$ composer require wikimedia/dodo
```

## Usage

A better set of examples and tests is coming. For an extremely basic
usage, see [test/demo.php](test/demo.php).

Change directories to `test` and run:
```
php demo.php
```

## Tests

```bash
$ composer test
```

## Status

This software is a work in progress. Prioritized *TODO*s:

1. Porting of the [W3C DOM Test Suite](https://www.w3.org/DOM/Test/)
2. Porting of the [WHATWG test suite](https://wiki.whatwg.org/wiki/Testsuite)
4. Integration with [RemexHtml](https://gerrit.wikimedia.org/g/mediawiki/libs/RemexHtml/)
5. Integration with [zest.php](https://github.com/cscott/zest.php/tree/master)
6. Performance benchmarks
7. Cutting out things (even if they're in the spec) that are irrelevant to [Parsoid](https://www.mediawiki.org/wiki/Parsoid)
8. "Dynamic" generation of HTML classes from a spec (htmlelts.js); see
   (mediawiki/webidl)[https://github.com/wikimedia/mediawiki-libs-WebIDL] and
   (mediawiki/idle-dom)[https://github.com/wikimedia/mediawiki-libs-IDLeDOM];
   this just needs to be extended to handle the WebIDL intefaces defined in
   the HTML spec and some special features like attribute reflection.

## Background

(taken from [this page](https://www.mediawiki.org/wiki/Parsoid/PHP/Help_wanted))

> The PHP DOM extension is a wrapper around libxml2 with a thin layer of DOM-compatibility on top ("To some extent libxml2 provides support for the following additional specifications but doesn't claim to implement them completely [...] Document Object Model (DOM) Level 2 Core [...] but it doesn't implement the API itself, gdome2 does this on top of libxml2").

> This is not really remotely close to a modern standards-compliant HTML5 DOM implementation and is barely maintained, much less kept in sync with the WHATWG's pace of change.

The Dodo library implements PHP interfaces generated directly from the
WebIDL sources included in the WHATWG DOM specification by `IDLeDOM`.

## Developer Notes

### Why you need accessors for interface properties

Most DOM implementations have to make a decision about adapting the
specification's notion of an interface property. In many languages,
the only solution is to use accessor functions, e.g. `getFoo()` and
`setFoo(value)` and prevent direct access to the properties themselves.

This is not contrary to the spec's intention, as it is mostly capturing
data representation, and seems to expect some level of indirection between
the library that implements the specification, and the code which calls
that library.

Aside from the usual arguments and reasons for preferring accessors
over direct property access and vice-versa, in this case most implementations
are forced down the accessor route for one reason in particular, and that is
that the current [DOM Specification](https://dom.spec.whatwg.org) defines
certain interface properties as being `readonly`, for example the
[Attr](https://dom.spec.whatwg.org/#interface-attr) interface:

```
interface Attr : Node {
  readonly attribute DOMString? namespaceURI;
  readonly attribute DOMString? prefix;
  readonly attribute DOMString localName;
  readonly attribute DOMString name;
  [CEReactions] attribute DOMString value;

  readonly attribute Element? ownerElement;

  readonly attribute boolean specified; // useless; always returns true
};
```

This essentially means that once their value has been set once (in the
constructor), it cannot be modified, but can still be accessed.

PHP currently lacks a way to implement readonly properties without
incurring significant performance penalties. Although there have been
several RFCs ([readonly properties](https://wiki.php.net/rfc/readonly_properties)
and [property accessors syntax](https://wiki.php.net/rfc/propertygetsetsyntax-v1.2)),
they have always been declined.

So, in this implementation, all properties have `protected` scope, and
each interface is equipped with either one or two accessors depending
on whether the specification marks it as `readonly` or not.
These accessors do not appear in the spec, and now you know why.

To be clear: if a class property "foo" is not marked `readonly`, then there
will be methods `getFoo()` and `setFoo($value)` defined on the class.
If "foo" is marked `readonly`, then only `getFoo()` will be defined on the
class.

An irritating side-effect here is that since the style of the spec obliges
us to use camelCase, by pre-pending the property names with `get` or `set`,
we force the first letter of the property to be uppercased, thus making
it even further removed from its naming in the spec.

So, if you're reading this, and PHP has passed an RFC with
readonly properties or type hints for class property definitions,
you know what to do.

### Use of accessors within the implementation

For performance reasons, accessors are not always used internally.
In the protected scope, things will access properties directly since
we have full control over the things they are doing and can verify
they are abiding by the constraints.

### Strings specified as "NULL or non-empty"

It isn't uncommon for interface properties to have type written
`DOMString?`, which is not a single type, but rather indicates
that the field may take either of type `NULL`, or type `DOMString`
(they are distinct types).

For example, the `namespaceURI` property from the [Attr](https://dom.spec.whatwg.org/#interface-attr) interface:

```
interface Attr : Node {
  readonly attribute DOMString? namespaceURI;
  /* ... */
};
```

However, it's common for there to be an additional constraint on the value of
such properties, one which is not visible from inspection of the interface
definition in IDL.

For example, [namespaceURI](https://dom.spec.whatwg.org/#dom-attr-namespaceuri)
is defined to return the
[namespace](https://dom.spec.whatwg.org/#concept-attribute-namespace), which is
either "NULL or a non-empty string".

Well, this is a bit annoying because it's certainly possible to provide the
empty string to any interface which accepts arguments of type `DOMString`.

Because of this common stipulation, you would find in the code something
that looks like:
```
class Attr extends Node
{
        protected $namespaceURI = NULL;

        public function construct(string? $namespace=NULL /* ... other arguments ... */)
        {
                if ($namespace !== '') {
                        $this->$namespaceURI = $namespace;
                }

                /* ... */
        }

        /* ... */
}
```
The caller can provide either a string or NULL, but the assignment
will only occur if it is NOT the empty string. In that case,
`$this->$namespaceURI` will retain its default value of `NULL`.

### Strings specified as "non-empty"

This seems simpler, but it's actually worse than "NULL or non-empty"!

Properties that must be "non-empty strings", such as
[localName](https://dom.spec.whatwg.org/#concept-attribute-local-name),
are usually integral to the object functioning properly. `localName`, for
example, is the name of the attribute.

Unfortunately, an empty string is also a string, and even a `DOMString`. So
providing the empty string is valid when the function's argument type
is `DOMString` (or `string`, in PHP's type hinting). But in the case of
constructors, once we find out that this argument is the empty string,
the entire object is undefined.

But in PHP, it's not possible to "abort" the constructor -- an object of the
specified class will always be returned to the caller.
In old versions of PHP, you could actually do something like `unset($this)`
inside the constructor. Pretty cool, but you haven't been able to do it for
years. What a pain...

So we probably have to throw an Exception, or make a "non-empty string" class.

### Readonly does not mean immutable

Read-only/read-write and mutable/immutable

These are not equivalent, though it seems at first they might be.

        Immutable <=> read-only
        Read-write => mutable

But

        mutable =/> read-write

For example, on an Attr object, ownerElement is a read-only property,
but it can still change if we associate the Attr node with another
element.

For another example, the name property of an attribute is read-only,
but the prefix property is read-write, and since I can mutate the prefix
property, I can mutate the name (which includes this prefix), thus
making the name property mutable, even if it's read-only.

Basically, there are properties where even if you can't update them
directly, you can update something that is used to compute their value.


### Methods that are somewhere between abstract and concrete...

The `Node` interface methods `isEqualNode` and `cloneNode` are two good
examples of things that are annoying. Both of them first do something
that is common among all `Node` objects, and then proceed to do something
that is unique to whatever class has extended `Node`, for example `Attr`.

That means that if you want to implement them as `abstract`, you have
to include this boilerplate `Node`-common stuff in all of the subclass
implementations of the abstract method. What a pain.

So instead, we have abstract methods like `_subclass_isEqualNode`, which
are called by `Node::isEqualNode` when it's time to do the subclass-specific
part.


Hmm, maybe the real reason this is coming up is that things like
`isEqualNode` also recurse, and the children may be several different
classes that extend Node.


### Other readability conventions

- If a property is part of the spec, it is written exactly as in the spec
IDL.
- If a property or method is for internal-use, it is prefixed with '_'.


### Properties which are not spec-compliant if accessed directly

For why, see their definition in the code.

```
Node::_nextSibling
Node::_previousSibling
Node::_childNodes
Node::_firstChild -- is set to NULL when _childNodes is not NULL.
```

### Potential bugs in Domino.js

It appears that HTMLCollection will not recompute the cache
when an Element's `id` or `name` attribute changes. However,
these are used to index two internal caches, and so the HTMLCollection
will no longer be "live".

Solution would be to update `lastModTime` when those attributes are
mutated.

### Performance tips

- Make sure your Element ids stay unique. The spec requires that you
  return the first Element with that id, in document order, and it is
  not very performant to compute the document order.

## License and Credits

The initial version of this code was written by Jason Linehan and is
(c) Copyright 2019 Wikimedia Foundation.

This code is distributed under the MIT license; see LICENSE for more
info.

---
[Latest Stable Version]: https://poser.pugx.org/wikimedia/dodo/v/stable.svg
[License]: https://poser.pugx.org/wikimedia/dodo/license.svg
