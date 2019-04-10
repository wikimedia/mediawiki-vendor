<?php

namespace Wikibase\TermStore\Implementations;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\TermStore\ItemTermStore;
use Wikibase\TermStore\TermStoreException;

class InMemoryItemTermStore implements ItemTermStore {

	private $fingerprints = [];

	/**
	 * @throws TermStoreException
	 */
	public function storeTerms( ItemId $itemId, Fingerprint $terms ) {
		$this->fingerprints[$itemId->getNumericId()] = $terms;
	}

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( ItemId $itemId ) {
		unset( $this->fingerprints[$itemId->getNumericId()] );
	}

	/**
	 * @throws TermStoreException
	 */
	public function getTerms( ItemId $itemId ): Fingerprint {
		if ( array_key_exists( $itemId->getNumericId(), $this->fingerprints ) ) {
			return $this->fingerprints[$itemId->getNumericId()];
		}

		return new Fingerprint();
	}

}