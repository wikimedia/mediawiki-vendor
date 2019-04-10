<?php

namespace Wikibase\TermStore\Implementations;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\TermStore\PropertyTermStore;
use Wikibase\TermStore\TermStoreException;

class ThrowingPropertyTermStore implements PropertyTermStore {

	/**
	 * @throws TermStoreException
	 */
	public function storeTerms( PropertyId $propertyId, Fingerprint $terms ) {
		throw new TermStoreException();
	}

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( PropertyId $propertyId ) {
		throw new TermStoreException();
	}

	/**
	 * @throws TermStoreException
	 */
	public function getTerms( PropertyId $propertyId ): Fingerprint {
		throw new TermStoreException();
	}

}