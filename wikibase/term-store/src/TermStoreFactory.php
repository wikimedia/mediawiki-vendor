<?php

namespace Wikibase\TermStore;

interface TermStoreFactory {

	public function newPropertyTermStore(): PropertyTermStore;

	public function newItemTermStore(): ItemTermStore;

}
