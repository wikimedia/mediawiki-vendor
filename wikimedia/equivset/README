# Equivset
A mapping of "equivalent" characters to prevent spoofing.

## Usage
```php
use Wikimedia\Equivset\Equivset;

$equivset = new Equivset();

// Normalize a string
echo $equivset->normalize( 0 ); // O

// Get a single character.
if ( $equivset->has( 'a' ) ) {
	$char = equivset->get( 'a' );
}

// Loop over entire set.
foreach ( $equivset as $char => $equiv ) {
	// Do something.
}

// Get the entire set.
$all = $equivset->all();
```

## Contributing
All changes should be made to `./data/equivset.in`. Then run
`bin/console generate-equivset` to generate the JSON, serialized, and plain
text versions of the equivset in `./dist`.
