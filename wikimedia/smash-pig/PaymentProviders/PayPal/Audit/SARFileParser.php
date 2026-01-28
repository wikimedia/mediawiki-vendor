<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\NormalizationException;
use SmashPig\Core\UnhandledException;

/**
 * Parser for SAR files.
 *
 * Handles Subscription Agreement Report. (SAR) from PayPal.
 *
 * Rows provide detail on subscription life cycle.
 *
 * @see https://www.paypalobjects.com/webstatic/en_US/developer/docs/pdf/PP_LRD_SubscribeAgmntRprt.pdf
 */
class SARFileParser extends BaseParser {

	// Action types from the SAR report
	private const ACTION_SIGNUP = 'S0000';
	private const ACTION_MODIFY = 'S0100';
	private const ACTION_CANCEL = 'S0200';
	private const ACTION_EOT = 'S0300';

	/**
	 * Build a normalized recurring message from a SAR row.
	 *
	 * @throws NormalizationException for malformed/unexpected data that should be treated as an error
	 * @throws UnhandledException for rows we intentionally skip (e.g., modify rows)
	 */
	public function getMessage(): array {
		$this->assertRequiredFieldsPresent();

		$names = $this->splitName( (string)$this->row['Subscription Payer Name'] );
		$date = $this->parseSarDateToUnix( (string)$this->row['Subscription Creation Date'] );

		$msg = [
			'subscr_id' => (string)$this->row['Subscription ID'],
			'currency' => (string)$this->row['Subscription Currency'],
			'gross' => ( (float)$this->row['Period 3 Amount'] ) / 100.0,
			'email' => (string)( $this->row['Subscription Payer email address'] ?? '' ),
			'first_name' => $names['first_name'],
			'last_name' => $names['last_name'],
			'street_address' => (string)( $this->row['Shipping Address Line1'] ?? '' ),
			'city' => (string)( $this->row['Shipping Address City'] ?? '' ),
			'postal_code' => (string)( $this->row['Shipping Address Zip'] ?? '' ),
			'state_province' => (string)( $this->row['Shipping Address State'] ?? '' ),
			'country' => (string)( $this->row['Shipping Address Country'] ?? '' ),
			'gateway' => 'paypal', // TODO: detect paypal_ec if/when SAR contains reliable signal
		];

		$this->addFrequency( $msg );

		$action = (string)$this->row['Subscription Action Type'];
		if ( $action === self::ACTION_SIGNUP ) {
			$msg['txn_type'] = 'subscr_signup';
			$msg['start_date'] = $date;
			$msg['create_date'] = $date;
			return $msg;
		}

		if ( $action === self::ACTION_MODIFY ) {
			// Python: ignore modify
			throw new UnhandledException( 'Subscription modify row ignored' );
		}

		if ( $action === self::ACTION_CANCEL ) {
			$msg['txn_type'] = 'subscr_cancel';
			$msg['cancel_date'] = $date;
			return $msg;
		}

		if ( $action === self::ACTION_EOT ) {
			$msg['txn_type'] = 'subscr_eot';
			return $msg;
		}

		throw new NormalizationException(
			'Unknown Subscription Action Type: ' . $action
		);
	}

	/**
	 * @throws NormalizationException
	 */
	private function assertRequiredFieldsPresent(): void {
		$requiredFields = [
			'Period 3 Amount',
			'Subscription Currency',
			'Subscription ID',
			'Subscription Payer Name',
			'Subscription Period 3',
			'Subscription Creation Date',
			'Subscription Action Type',
		];

		$missing = [];
		foreach ( $requiredFields as $field ) {
			if ( !array_key_exists( $field, $this->row ) || trim( (string)$this->row[$field] ) === '' ) {
				$missing[] = $field;
			}
		}

		if ( $missing ) {
			throw new NormalizationException(
				'Message is missing some important fields: [' . implode( ', ', $missing ) . ']'
			);
		}
	}

	private function splitName( string $fullName ): array {
		$fullName = trim( $fullName );
		if ( $fullName === '' ) {
			return [ 'first_name' => '', 'last_name' => '' ];
		}

		$parts = preg_split( '/\s+/', $fullName ) ?: [];
		$first = $parts[0] ?? '';
		$last = count( $parts ) > 1 ? implode( ' ', array_slice( $parts, 1 ) ) : '';

		return [ 'first_name' => $first, 'last_name' => $last ];
	}

	/**
	 * Map SAR "Subscription Period 3" to frequency_interval/unit.
	 *
	 * @throws NormalizationException
	 */
	private function addFrequency( array &$msg ): void {
		$period = (string)$this->row['Subscription Period 3'];

		if ( $period === '1 M' ) {
			$msg['frequency_interval'] = '1';
			$msg['frequency_unit'] = 'month';
			return;
		}

		if ( $period === '1 Y' ) {
			$msg['frequency_interval'] = '1';
			$msg['frequency_unit'] = 'year';
			return;
		}

		throw new NormalizationException( 'Unknown subscription period ' . $period );
	}

	/**
	 * SAR dates look like TRR dates in your fixtures (e.g. "2017/03/22 09:34:59 -0700")
	 * but some SAR exports are date-only. We accept anything strtotime can parse.
	 *
	 * @throws NormalizationException
	 */
	private function parseSarDateToUnix( string $dateStr ): int {
		$ts = strtotime( $dateStr );
		if ( $ts === false ) {
			throw new NormalizationException( 'Could not parse date: ' . $dateStr );
		}
		return $ts;
	}
}
