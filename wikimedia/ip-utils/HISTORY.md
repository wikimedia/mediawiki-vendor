# Release History

## 6.0.0
* Add benchmark for IPUtils::isInRanges (Ori Livneh)
* Add test coverage for trim operation in parseRange() (Ori Livneh)
* Add tests for isInRange (Ori Livneh)
* Avoid comparing IPs and ranges of different families (Ori Livneh)
* [BREAKING CHANGE] Drop support for PHP < 8.1 (James D. Forrester)
* composer.json: Add ext-json to require (Reedy)
* Extract IPv4 logic from parseCIDR into parseCIDR4 helper (Ori Livneh)
* Extract toHex4/toHex6 helpers from toHex (Ori Livneh)
* .gitattributes: Add benchmarks folder and alphasort (Sam Reed)
* IPUtils: Minor cleanup, mostly comments (Sam Reed)
* IPUtils: Remove !== false check from parseCIDR6 (Sam Reed)
* Optimize isInRanges by calling toHex() only once (Ori Livneh)
* Optimize isIPAddress by removing combined regex (Ori Livneh)
* Remove extra case for cidr /0 in parseCIDR (Umherirrender)
* Remove redundant isIPAddress check in sanitizeIP (Ori Livneh)
* Remove redundant sanitizeIPv6 call from parseRange6 (Ori Livneh)
* Remove redundant sanitizeIPv6 call in convertIPv6ToRawHex (Ori Livneh)
* Rename private function to match lowerCamelCase style (Umherirrender)
* Simplify phan config (Reedy)
* Split sanitizeIP into private IPv4/IPv6 helpers (Ori Livneh)
* Use php8 functions str_contains and str_starts_with (Umherirrender)
* Use str_split in hexToOctet/hexToQuad (Umherirrender)

## 5.0.0
* Require PHP 7.4 or later (Timo Tijhof)
* The IPSet class is now part of the IPUtils library (Timo Tijhof)

## 4.0.1
* Allow wikimedia/ip-set ^4.0.0 (Timo Tijhof)

## 4.0.0
* Several IPUtils constants are now explicitly private (Zabe)

## 3.0.2
* Remove redundant strpos/substr code from canonicalize() (Reedy)
* Allow wikimedia/ip-set ^3.0.0 (Reedy)

## 3.0.1
* Revert "Stop allowing invalid /0 subnet" (Reedy)
* Update return type for IPUtils::parseCIDR6 (Reedy)
* Allow spaces around hyphenated IP ranges (Reedy)

## 3.0.0
* Add method to retrieve all IPs in a given range (Ammar Abdulhamid)
* Cast isValidIPv[46] return value to bool (Reedy)

## 2.0.0
* Add isValidIPv4 and isValidIPv6 (Reedy)
* Allow explicit ranges in IPUtils::isValidRange() (Reedy)
* Check for IPv4 before IPv6 in IPUtils::isValidRange (Reedy)
* Combine hexToOctet and hexToQuad tests to cover formatHex (Reedy)
* Drop PHP 7.0/7.1 and HHVM support (James D. Forrester)
* Fix return values where function doesn't always return [string, string] (Reedy)
* Make getSubnet actually not accept invalid IPv4 addresses (Reedy)
* Remove unnecessary temporary variables (Reedy)
* Stop allowing invalid /0 subnet (Reedy)

## 1.0.0
* Initial import from MediaWiki core (Kunal Mehta)
