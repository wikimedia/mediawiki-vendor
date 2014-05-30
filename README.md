MediaWiki-Core-Vendor
=====================

[Composer] managed libraries for use with [MediaWiki] on the Wikimedia
Foundation production and testing clusters.

Adding or updating libraries
----------------------------

1. Edit the composer.json file
2. Run `composer update --optimize-autoloader` to download files and update
   the autoloader files.
3. Add and commit changes as a gerrit patch.
4. Review and merge changes.


[Composer]: https://getcomposer.org/
[MediaWiki]: https://www.mediawiki.org/wiki/MediaWiki
