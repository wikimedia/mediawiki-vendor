<?php namespace SmashPig\PaymentProviders\Ingenico\Audit;

use DOMElement;
use RuntimeException;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Ingenico\ReferenceData;
use XMLReader;

class IngenicoAudit implements AuditParser {

	protected $fileData;

	protected $donationMap = [
		'PaymentAmount' => 'gross',
		'IPAddressCustomer' => 'user_ip',
		'BillingFirstname' => 'first_name',
		'BillingSurname' => 'last_name',
		'BillingStreet' => 'street_address',
		'BillingCity' => 'city',
		'ZipCode' => 'postal_code',
		'BillingCountryCode' => 'country',
		'BillingEmail' => 'email',
		'AdditionalReference' => 'invoice_id',
		'PaymentProductId' => 'gc_product_id',
		'PaymentReference' => 'gc_payment_reference',
		'OrderID' => 'order_id',
		'MerchantID' => 'gateway_account',
		// Ingenico recurring donations all have the same OrderID
		// We can only tell them apart by the EffortID, which we
		// might as well normalize to 'installment'.
		'EffortID' => 'installment',
		'AttemptID' => 'attempt_id',
		'PaymentCurrency' => 'currency',
		'AmountLocal' => 'gross',
		'CurrencyLocal' => 'currency',
		'DateDue' => 'date',
		// Order matters. Prefer TransactionDateTime if it is present.
		'TransactionDateTime' => 'date',
	];

	protected $refundMap = [
		'AmountLocal' => 'gross',
		'CurrencyLocal' => 'gross_currency',
		'DebitedAmount' => 'gross',
		'AdditionalReference' => 'invoice_id',
		'OrderID' => 'gateway_parent_id',
		'MerchantID' => 'gateway_account',
		'EffortID' => 'installment',
		'AttemptID' => 'attempt_id',
		'DebitedCurrency' => 'gross_currency',
		'DateDue' => 'date',
		// Order matters. Prefer TransactionDateTime if it is present.
		'TransactionDateTime' => 'date',
	];

	protected $recordsWeCanDealWith = [
		// Credit card item that has been processed, but not settled.
		// We take these seriously.
		// TODO: Why aren't we waiting for +ON (settled)?
		'XON' => 'donation',
		// Settled "Invoice Payment". Could be invoice, bt, rtbt, check,
		// prepaid card, ew, cash
		'+IP' => 'donation',
		'-CB' => 'chargeback', // Credit card chargeback
		'-CR' => 'refund', // Refund on collected credit card payment
		'XCR' => 'refund', // Any old refund
		'+AP' => 'donation', // Direct Debit collected
	];

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$unzippedFullPath = $this->getUnzippedFile( $path );

		Logger::info( "Opening $unzippedFullPath with XMLReader" );
		$reader = new XMLReader();
		$reader->open( $unzippedFullPath );
		Logger::info( "Processing" );
		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->name == 'tns:DataRecord' ) {
				$record = $reader->expand();
				$this->parseRecord( $record );
			}
		}
		$reader->close();
		unlink( $unzippedFullPath );

		return $this->fileData;
	}

	protected function parseRecord( DOMElement $recordNode ) {
		$category = $recordNode->getElementsByTagName( 'Recordcategory' )
			->item( 0 )->nodeValue;
		$type = $recordNode->getElementsByTagName( 'Recordtype' )
			->item( 0 )->nodeValue;

		$compoundType = $category . $type;
		if ( !array_key_exists( $compoundType, $this->recordsWeCanDealWith ) ) {
			return;
		}

		$gateway = $this->getGateway( $recordNode );

		if ( $category === '-' || $compoundType === 'XCR' ) {
			$refundType = $this->recordsWeCanDealWith[$compoundType];
			$record = $this->parseRefund( $recordNode, $refundType, $gateway );
		} else {
			$record = $this->parseDonation( $recordNode, $gateway );
		}
		$record['gateway'] = $gateway;
		$record = $this->normalizeValues( $record );

		$this->fileData[] = $record;
	}

	protected function parseDonation( DOMElement $recordNode, string $gateway ): array {
		$record = $this->xmlToArray( $recordNode, $this->donationMap );
		if ( $record['order_id'] === '0' && !empty( $record['gc_payment_reference'] ) ) {
			$record['order_id'] = $record['gc_payment_reference'];
		}
		unset( $record['gc_payment_reference'] );
		if ( $gateway === 'globalcollect' ) {
			$record['gateway_txn_id'] = $record['order_id'];
		} else {
			$record['gateway_txn_id'] = $this->getConnectPaymentId( $record );
		}
		$record = $this->addPaymentMethod( $record );
		if ( $record['installment'] > 1 ) {
			$record['recurring'] = 1;
			// If $record['installment'] == 1, we may have a normal one-time
			// payment, or the first payment of a recurring donation.
			// This logic is sufficient for WMF's purposes, because we're only
			// using the 'recurring' flag parsed out of the audit file to make
			// sure donations after the first one are correctly inserted rather
			// than dropped as duplicates of the first donation.
			// We'll determine the recurring-ness of donations where
			// installment=1 when we parse our logs looking for the order type.
		}
		return $record;
	}

	/**
	 * @param \DOMElement $recordNode
	 * @param string $type
	 * @param string $gateway
	 *
	 * @return array
	 *
	 * TODO: for refunds of recurring payments, determine whether the
	 * refund's EffortID is always the negative of the corresponding
	 * installment's EffortID. We want to know which one we refunded.
	 *
	 */
	protected function parseRefund( DOMElement $recordNode, string $type, string $gateway ): array {
		$record = $this->xmlToArray( $recordNode, $this->refundMap );
		$record['type'] = $type;

		// deal with negative EffortID
		if ( !empty( $record['installment'] ) ) {
			$record['installment'] = abs( $record['installment'] );
		}

		// determine parent_id format by gateway version
		if ( $gateway === 'ingenico' ) {
			$record['gateway_parent_id'] = $this->getConnectPaymentId( $record );
		} else {
			if ( $record['installment'] > 1 ) {
				$record['gateway_parent_id'] .= '-' . $record['installment'];
			}
		}
		// XCR records give us a negative amount
		if ( $record['gross'] < 0 ) {
			$record['gross'] = $record['gross'] * -1;
		}

		// FIXME: Refund ID is the same as the parent transaction ID.
		// That's not helpful...
		$record['gateway_refund_id'] = $record['gateway_parent_id'];
		return $record;
	}

	protected function xmlToArray( DOMElement $recordNode, array $map ): array {
		$record = [];
		foreach ( $map as $theirs => $ours ) {
			foreach ( $recordNode->getElementsByTagName( $theirs ) as $recordItem ) {
				$record[$ours] = $recordItem->nodeValue;  // there 'ya go: Normal already.
			}
		}
		return $record;
	}

	/**
	 * Adds our normalized payment_method and payment_submethod params based
	 * on the codes that GC uses
	 *
	 * @param array $record The record from the wx file, in array format
	 * @return array The $record param with our normal keys appended
	 */
	public function addPaymentMethod( array $record ): array {
		$normalized = ReferenceData::decodePaymentMethod(
			$record['gc_product_id']
		);
		$record = array_merge( $record, $normalized );

		unset( $record['gc_product_id'] );
		return $record;
	}

	/**
	 * @param string $path Path to original zipped file
	 * @return string Path to unzipped file in working directory
	 */
	protected function getUnzippedFile( string $path ): string {
		$zippedParts = explode( DIRECTORY_SEPARATOR, $path );
		$zippedFilename = array_pop( $zippedParts );
		// TODO keep unzipped files around?
		$workingDirectory = tempnam( sys_get_temp_dir(), 'ingenico_audit' );
		if ( file_exists( $workingDirectory ) ) {
			unlink( $workingDirectory );
		}
		mkdir( $workingDirectory );
		// whack the .gz on the end
		$unzippedFilename = substr( $zippedFilename, 0, strlen( $zippedFilename ) - 3 );

		$copiedZipPath = $workingDirectory . DIRECTORY_SEPARATOR . $zippedFilename;
		copy( $path, $copiedZipPath );
		if ( !file_exists( $copiedZipPath ) ) {
			throw new RuntimeException(
				"FILE PROBLEM: Trying to copy $path to $copiedZipPath " .
				'for decompression, and something went wrong'
			);
		}

		$unzippedFullPath = $workingDirectory . DIRECTORY_SEPARATOR . $unzippedFilename;
		// decompress
		Logger::info( "Gunzipping $copiedZipPath" );
		// FIXME portability
		$cmd = "gunzip -f $copiedZipPath";
		exec( escapeshellcmd( $cmd ) );

		// now check to make sure the file you expect actually exists
		if ( !file_exists( $unzippedFullPath ) ) {
			throw new RuntimeException(
				'FILE PROBLEM: Something went wrong with decompressing WX file: ' .
				"$cmd : $unzippedFullPath doesn't exist."
			);
		}
		return $unzippedFullPath;
	}

	protected function getConnectPaymentId( array $record ): string {
		$merchantId = str_pad(
			$record['gateway_account'], 10, '0', STR_PAD_LEFT
		);
		$orderId = isset( $record['order_id'] ) ? $record['order_id'] :
			$record['gateway_parent_id'];
		$orderId = str_pad(
			$orderId, 10, '0', STR_PAD_LEFT
		);
		$effortId = str_pad(
			$record['installment'], 5, '0', STR_PAD_LEFT
		);
		$attemptId = str_pad(
			$record['attempt_id'], 5, '0', STR_PAD_LEFT
		);
		return "$merchantId$orderId$effortId$attemptId";
	}

	/**
	 * Normalize amounts, dates, and IDs to match everything else in SmashPig
	 * FIXME: do this with transformers migrated in from DonationInterface
	 *
	 * @param array $record
	 * @return array The record, with values normalized
	 */
	protected function normalizeValues( array $record ): array {
		if ( isset( $record['gross'] ) ) {
			$record['gross'] = $record['gross'] / 100;
		}
		if ( isset( $record['invoice_id'] ) ) {
			$parts = explode( '.', $record['invoice_id'] );
			$record['contribution_tracking_id'] = $parts[0];
		}
		if ( isset( $record['date'] ) ) {
			$record['date'] = UtcDate::getUtcTimestamp( $record['date'] );
		}
		// Only used internally
		if ( isset( $record['attempt_id'] ) ) {
			unset( $record['attempt_id'] );
		}
		return $record;
	}

	protected function getGateway( DOMElement $recordNode ): string {
		// Heuristics to determine which API integration the txn came in on.
		$paymentProductNode = $recordNode->getElementsByTagName( 'PaymentProductId' );
		if ( $paymentProductNode->length > 0 ) {
			// Some products were only ever offered through the legacy integration
			$legacyOnlyProducts = [
				'500' // bpay
			];
			$paymentProductId = $paymentProductNode->item( 0 )->nodeValue;
			if ( in_array( $paymentProductId, $legacyOnlyProducts ) ) {
				return 'globalcollect';
			}
		}
		// Connect API transactions have EmailTypeIndicator, if they have Email
		$email = $recordNode->getElementsByTagName( 'Email' );
		$typeIndicator = $recordNode->getElementsByTagName( 'EmailTypeIndicator' );
		if ( $email->length > 0 ) {
			if ( $typeIndicator->length > 0 ) {
				return 'ingenico';
			}
			return 'globalcollect';
		}
		// Otherwise, we rely on the format of the AdditionalReference. It's got
		// 5 digits after the decimal point for old-API transactions.
		$arNode = $recordNode->getElementsByTagName( 'AdditionalReference' );
		if ( $arNode->length > 0 ) {
			$additionalReference = $arNode->item( 0 )->nodeValue;
			$parts = explode( '.', $additionalReference );
			if ( count( $parts ) === 2 ) {
				if ( strlen( $parts[1] ) === 5 ) {
					return 'globalcollect';
				}
				return 'ingenico';
			}
		}
		// No idea. Default to the new thing.
		return 'ingenico';
	}
}
