# Release History

## v2.3.1

* Port [upstream changes v2.3.1](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Fix unexpected flipping of percentages in functions in background shorthand (Aidan O'Brien). [T333273](https://phabricator.wikimedia.org/T333273)

## v2.3.0

* Port [upstream changes v2.3.0](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Don't change `:dir()` pseudo-selector target (Ebrahim Byagowi). [T371466](https://phabricator.wikimedia.org/T371466)

## v2.2.0

* Port [upstream changes v2.2.0](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Flip `calc()` in four value notation (Moh'd Khier Abualruz). [T369553](https://phabricator.wikimedia.org/T369553)

## v2.1.1

* This release requires PHP 7.4 or higher.

## v2.1.0

* Port [upstream changes v2.1.0](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Fix unexpected flipping in selectors with the general sibling combinator (Mainframe98). [T400635](https://phabricator.wikimedia.org/T400635)).

## v2.0.0

* This release requires PHP 7.2 or higher.
* Port [upstream changes v2.0.0](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Fix unexpected flipping in certain cases involving double quotes or comments in selectors (YairRand). [T400634](https://phabricator.wikimedia.org/T400634)

## v1.3.0

* This release requires PHP 5.6 or higher, tested up until PHP 7.3.
* Fix transformation breakage on PHP 7.0+ with PCRE 8.39+ (Brad Jorsch). [T215746](https://phabricator.wikimedia.org/T215746)
* Port [upstream changes v1.2.1...v1.3.2](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Fix unintended flipping of selectors containing a backslash (YairRand).
  * Fix bug where `transform` didn't flip on lines without semicolon (YairRand).

## v1.2.1

* This release requires PHP 5.5 or higher, tested up until PHP 7.1.

## v1.2.0

* This release requires PHP 5.4 or later.
* Add options parameter, boolean arguments remain supported (Timo Tijhof).
* Port [upstream changes v1.2.0](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Flip `translate(x[,y,z])` and `translateX(x)` (Ed Sanders).

## v1.1.3

* Port [upstream changes v1.1.3](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Do not flip offset-y in text-shadow, even when color isn't as first value (Ed Sanders).

## v1.1.2

* Port [upstream changes v1.1.2](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Support !important and slash in border-radius values (Dominik Schilling).

## v1.1.1

* Port [upstream changes v1.1.1](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/):
  * Support !important in four-value declarations (Matthew Flaschen).

## v1.1.0

Starting with the v1.1.0 release, php-cssjanus is considered a port of [node-cssjanus](https://gerrit.wikimedia.org/g/mediawiki/libs/node-cssjanus/) with a shared specification and shared semantic versioning to ensure feature parity.

* Support `/*!` syntax for `@noflip`.
* Flip `border-style`.
* Improve flipping of `background-position`.
* Restrict `four_notation_quantity` to margin, padding and border-width.
* Flip two values in `border-radius` rul, in addition to four values.

## v0.1.0

Moved from MediaWiki core 1.24alpha ([change 163074](https://gerrit.wikimedia.org/r/c/mediawiki/core/+/163074), [change 170107](https://gerrit.wikimedia.org/r/c/mediawiki/core/+/170107)) into its own repository and published to Packagist.

