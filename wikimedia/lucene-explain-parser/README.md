PHP Classes for parsing and pretty-printing Lucene explain structures.

Makes the data (more) human-readable.

This is all based on https://github.com/o19s/splainer-search, which does much nicer prints of lucene explains for splainer.io.

Usage:

```php
use LuceneExplain\ExplainFactory;

$factory = new ExplainFactory();
$explain = $factory->createExplain( $jsonFromLucene );
$prettyResult = (string)$explain;
```
