<?php

namespace SmashPig\PaymentProviders\dlocal\ApiMappers;

use SmashPig\PaymentProviders\dlocal\IAPIRequestMapper;

class ApiRequestMapper implements IAPIRequestMapper {
	/**
	 * Array containing the mapping of API params to the input array params
	 * Has the same structure as the required API array.
	 * [ key => value ]
	 * {{ key }} is the API property
	 * {{ value }} is the corresponding input array property
	 *
	 * @var array
	 */
	protected $parameterMap = [];

	/**
	 * Primary source array for retrieving the required API object values.
	 * This contains the request array.
	 * @var array
	 */
	protected $inputParameters;

	/**
	 * This contains the API array after mapping has been completed.
	 * @var array
	 */
	protected $outputParameters;

	/**
	 * @param array $parameters
	 * @return self
	 */
	public function setInputParams( array $parameters ): void {
		$this->inputParameters = $parameters;
	}

	/**
	 * Return the mapped API request object
	 * @return array
	 */
	public function getAll(): array {
		$this->transform();
		return $this->outputParameters;
	}

	/**
	 * Uses the $parameterMap definition to map the API specification to the input params
	 */
	public function transform(): void {
		$map = $this->getInputParameterMap();
		$input = $this->inputParameters;
		$output = $this->transformParams( $map, $input );
		$this->outputParameters = $this->setCustomParameters( $this->inputParameters, $output );
	}

	protected function getInputParameterMap(): array {
		return $this->parameterMap;
	}

	/**
	 * Some parameters may need additional formatting or mapping
	 * This formatting could be done in this method which the transform
	 * method calls.
	 * @param array $params
	 * @return array
	 */
	protected function setCustomParameters( array $params, &$mapOutput ): array {
		return $mapOutput;
	}

	/**
	 * Takes in the map spec and returns the API spec object populated with the corresponding
	 * input parameters
	 * @param array $map
	 * @param array $input
	 * @return array
	 */
	protected function transformParams( array $map, array $input ): array {
		$mapperOutput = [];

		foreach ( $map as $apiProperty => $value ) {
			if ( is_array( $value ) ) {
				$nestedParams = $this->transformParams( $value, $input );
				if ( count( $nestedParams ) > 0 ) {
					$mapperOutput[$apiProperty] = $nestedParams;
				}
				continue;
			}
			if ( array_key_exists( $value, $input ) ) {
				$mapperOutput[$apiProperty] = $input[$value];
			}
		}

		return $mapperOutput;
	}
}
