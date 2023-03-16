<?php

namespace SmashPig\PaymentProviders\dlocal;

interface IAPIRequestMapper {
	/**
	 * @return array
	 */
	public function getAll(): array;

	/**
	 * @param array $parameters
	 * @return self
	 */
	public function setInputParams( array $parameters ): void;

	/**
	 * @return self
	 */
	public function transform(): void;

}
