# Equivset

A mapping of "equivalent" characters to prevent spoofing.

## Installation
Using composer:
Add the following to the composer.json file for your project:
```
{
  "require": {
     "wikimedia/equivset": "^1.0.0"
  }
}
```
And then run 'composer update'.

## Usage

<pre lang="php">
use Wikimedia\Equivset\Equivset;

$equivset = new Equivset();

// Normalize a string
echo $equivset->normalize( 'sp00f' ); // SPOOF

// Get a single character.
if ( $equivset->has( 'ɑ' ) ) {
	$char = $equivset->get( 'ɑ' );
}
echo $char; // A

// Loop over entire set.
foreach ( $equivset as $char => $equiv ) {
	// Do something.
}

// Get the entire set.
$all = $equivset->all();
</pre>

## Contributing

All changes should be made to `./data/equivset.in`. Then run
`bin/console generate-equivset` to generate the JSON, serialized, and plain
text versions of the equivset in `./dist`.
