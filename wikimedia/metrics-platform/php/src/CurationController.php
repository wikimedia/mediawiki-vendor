<?php

namespace Wikimedia\MetricsPlatform;

use Wikimedia\MetricsPlatform\StreamConfig\StreamConfig;

class CurationController {

	/**
	 * Returns true if the event passes all curation rules in the stream configuration.
	 *
	 * @param array $event
	 * @param StreamConfig $streamConfig
	 * @return bool
	 */
	public function shouldProduceEvent( array $event, StreamConfig $streamConfig ): bool {
		$curationRules = $streamConfig->getCurationRules();

		foreach ( $curationRules as $property => $rules ) {
			[ $primaryKey, $secondaryKey ] = explode( '_', $property, 2 );

			if (
				!isset( $event[$primaryKey][$secondaryKey] )
				|| !$this->applyRules( $event[$primaryKey][$secondaryKey], $rules )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Apply configured rules to a context attribute.
	 *
	 * @param mixed $value
	 * @param array $rules
	 * @return bool
	 */
	private function applyRules( $value, array $rules ): bool {
		foreach ( $rules as $operator => $comparator ) {
			switch ( $operator ) {
				case 'equals':
					if ( $value !== $comparator ) {
						return false;
					}
					break;
				case 'not_equals':
					if ( $value === $comparator ) {
						return false;
					}
					break;
				case 'greater_than':
					if ( $value <= $comparator ) {
						return false;
					}
					break;
				case 'less_than':
					if ( $value >= $comparator ) {
						return false;
					}
					break;
				case 'greater_than_or_equals':
					if ( $value < $comparator ) {
						return false;
					}
					break;
				case 'less_than_or_equals':
					if ( $value > $comparator ) {
						return false;
					}
					break;
				case 'in':
					if ( !in_array( $value, $comparator ) ) {
						return false;
					}
					break;
				case 'not_in':
					if ( in_array( $value, $comparator ) ) {
						return false;
					}
					break;
				case 'contains':
					if ( !in_array( $comparator, $value ) ) {
						return false;
					}
					break;
				case 'does_not_contain':
					if ( in_array( $comparator, $value ) ) {
						return false;
					}
					break;
				case 'contains_all':
					if ( count( array_diff( $comparator, $value ) ) > 0 ) {
						return false;
					}
					break;
				case 'contains_any':
					if ( count( array_intersect( $comparator, $value ) ) === 0 ) {
						return false;
					}
					break;
				default:
					break;
			}
		}
		return true;
	}
}
