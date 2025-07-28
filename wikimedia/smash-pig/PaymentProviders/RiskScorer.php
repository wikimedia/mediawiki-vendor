<?php

namespace SmashPig\PaymentProviders;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

class RiskScorer {

	/** @var array */
	protected $avsMap;

	/** @var array */
	protected $cvvMap;

	public function __construct() {
		$config = Context::get()->getProviderConfiguration();
		$this->avsMap = $config->val( 'fraud-filters/avs-map' );
		$this->cvvMap = $config->val( 'fraud-filters/cvv-map' );
	}

	public function getRiskScores( ?string $avsResult, ?string $cvvResult ): array {
		if ( ( $avsResult === '' || $avsResult === null ) && ( $cvvResult === '' || $cvvResult === null ) ) {
			Logger::warning( 'Both AVS and CVV results are empty' );
			return [];
		}

		$scores = [];
		if ( $cvvResult !== null ) {
			$cvvResult = $this->trim( $cvvResult );
			if ( array_key_exists( $cvvResult, $this->cvvMap ) ) {
				$scores['cvv'] = $cvvScore = $this->cvvMap[$cvvResult];
				Logger::debug( "CVV result '$cvvResult' adds risk score $cvvScore." );
			} else {
				Logger::warning( "CVV result '$cvvResult' not found in cvv-map.", $this->cvvMap );
			}
		}

		if ( $avsResult !== null ) {
			$avsResult = $this->trim( $avsResult );
			if ( array_key_exists( $avsResult, $this->avsMap ) ) {
				$scores['avs'] = $avsScore = $this->avsMap[$avsResult];
				Logger::debug( "AVS result '$avsResult' adds risk score $avsScore." );
			} else {
				Logger::warning( "AVS result '$avsResult' not found in avs-map.", $this->avsMap );
			}
		}

		return $scores;
	}

	/**
	 * Trim off everything after the first segment
	 *
	 * @param string $rawResultCode
	 * @return string
	 */
	protected function trim( string $rawResultCode ): string {
		return explode( ' ', $rawResultCode, 2 )[0];
	}
}
