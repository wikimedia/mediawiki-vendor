<?php

namespace Wikibase\TermStore;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Term\Fingerprint;

interface ItemTermStore {

	/**
	 * Updates the stored terms for the specified item.
	 * @throws TermStoreException
	 */
	public function storeTerms( ItemId $itemId, Fingerprint $terms );

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( ItemId $itemId );

	/**
	 * Returns an empty Fingerprint when no terms are found.
	 * @throws TermStoreException
	 */
	public function getTerms( ItemId $itemId ): Fingerprint;

}
