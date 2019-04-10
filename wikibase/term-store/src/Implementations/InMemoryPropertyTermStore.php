<?php

namespace Wikibase\TermStore\Implementations;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\TermStore\PropertyTermStore;
use Wikibase\TermStore\TermStoreException;

class InMemoryPropertyTermStore implements PropertyTermStore {

	private $fingerprints = [];

	/**
	 * @throws TermStoreException
	 */
	public function storeTerms( PropertyId $propertyId, Fingerprint $terms ) {
		$this->fingerprints[$propertyId->getNumericId()] = $terms;
	}

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( PropertyId $propertyId ) {
		unset( $this->fingerprints[$propertyId->getNumericId()] );
	}

	/**
	 * @throws TermStoreException
	 */
	public function getTerms( PropertyId $propertyId ): Fingerprint {
		if ( array_key_exists( $propertyId->getNumericId(), $this->fingerprints ) ) {
			return $this->fingerprints[$propertyId->getNumericId()];
		}

		return new Fingerprint();
	}

}