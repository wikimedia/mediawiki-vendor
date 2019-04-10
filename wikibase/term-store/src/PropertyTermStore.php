<?php

namespace Wikibase\TermStore;

use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Fingerprint;

interface PropertyTermStore {

	/**
	 * Updates the stored terms for the specified property.
	 * @throws TermStoreException
	 */
	public function storeTerms( PropertyId $propertyId, Fingerprint $terms );

	/**
	 * @throws TermStoreException
	 */
	public function deleteTerms( PropertyId $propertyId );

	/**
	 * Returns an empty Fingerprint when no terms are found.
	 * @throws TermStoreException
	 */
	public function getTerms( PropertyId $propertyId ): Fingerprint;

}
