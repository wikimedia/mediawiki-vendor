<?php namespace SmashPig\Core\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class Request extends SymfonyRequest {
	public function getRawRequest(): string {
		return file_get_contents( 'php://input' );
	}

	/**
	 * XXX It's weird that we wrap the symfony helper just to export the
	 * request as an array. Worth the extra layer of indirection?
	 */
	public function getValues(): array {
		return $this->query->all() +
			$this->attributes->all() +
			$this->request->all();
	}
}
