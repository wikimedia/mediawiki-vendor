<?php

namespace Wikibase\TermStore\Implementations;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;
use Wikibase\TermStore\ItemTermStore;
use Wikibase\TermStore\TermStoreException;

class ThrowingItemTermStore implements ItemTermStore {

	/**
	 * @throws TermStoreException
	 */
	public function storeTerms( ItemId $itemId, Fingerprint $terms ) {
		throw new TermStoreException();
	}

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( ItemId $itemId ) {
		throw new TermStoreException();
	}

	/**
	 * @throws TermStoreException
	 */
	public function getTerms( ItemId $itemId ): Fingerprint {
		throw new TermStoreException();
	}

}