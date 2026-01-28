<?php

namespace SmashPig\PaymentProviders\Braintree\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class SearchTransactions extends MaintenanceBase {

	protected array $fileData;

	protected array $files = [];

	/**
	 * Now - we set this once so it doesn't move while running
	 *
	 * @var string
	 */
	protected string $now;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'hours', 'search transactions from how many hours till now', '24', 'r' );
		$this->addOption( 'start-date', 'search transactions starting from (alternative to hours)', '', 's' );
		$this->addOption( 'end-date', 'search transactions ending at (alternative to hours)', '', 'e' );
		$this->addOption( 'type', 'search what type of transactions (donation, refund, chargeback)', 'all', 't' );
		$this->addOption( 'path', 'location to store the reports', './private/wmf_audit/braintree/incoming', 'p' );
		$this->addOption( 'date-type', 'Type of date to query on - settled|created|disbursement|received', 'created', 'd' );
		$this->addFlag( 'raw', 'log raw data', 'v' );
		$this->addFlag( 'output-raw', 'output raw data', 'o' );
		$this->addFlag( 'consolidate', 'output to one consolidated file', 'c' );
		$this->desiredOptions['config-node']['default'] = 'braintree';
		$this->now = date( 'c' );
	}

	public function __destruct() {
		foreach ( $this->files  as $file ) {
			fclose( $file );
		}
	}

	/**
	 * Do the actual work of the script.
	 * @return void
	 */
	public function execute(): void {
		$type = $this->getOption( 'type' );
		$path = $this->getOption( 'path' );

		$greaterThan = $this->getStartDate();
		$greaterThanDate = substr( $greaterThan, 0, 10 );
		$endDate = $this->getEndDate();
		// get yesterday's or how many hrs from now's transaction, refunds and disputes
		Logger::info( "Get $type report from $greaterThanDate to $endDate\n" );
		if ( is_dir( $path ) ) {
			$provider = PaymentProviderFactory::getProviderForMethod( 'search' );

			$input = $this->getInput( $greaterThan );
			$after = null;
			if ( $this->isRunDonationSettlementReport() ) {
				$this->normalizeTransactions( $provider->searchTransactions( $input, $after ), 'donation' );
			}
			if ( $this->isRunRefundReport() ) {
				$this->normalizeTransactions( $provider->searchRefunds( $input, $after ), 'refund' );
			}
			if ( $this->isRunChargebackReport() ) {
				// For disputes we can use effectiveDate and receiveDate - the receiveDate is the date the
				// dispute started and the effectiveDate is the change of status - so we are looking for
				// things that changed status to lost. This is generally a day before the settlement date
				// so we look at the day before the main query date.
				$greaterThanDate = substr( $this->getDayBeforeStartDate(), 0, 10 );
				$lessThanDate = gmdate( 'Y-m-d', strtotime( $this->getDayBeforeEndDate() ) );
				$disputeInput = [
					"effectiveDate" => [ "greaterThanOrEqualTo" => $greaterThanDate, "lessThanOrEqualTo" => $lessThanDate ],
					"status" => [ "is" => "LOST" ],
				];
				$this->normalizeTransactions( $provider->searchDisputes( $disputeInput, $after ), 'chargeback' );
			}

			foreach ( array_keys( $this->fileData ) as $context ) {
				$this->writeToFile( $context );
			}
		} else {
			echo "incoming dir does not exist\n";
		}
	}

	/**
	 * @param ?string $invoice
	 * get the base transaction id
	 * @return string
	 */
	private function getContributionTrackingId( ?string $invoice ): string {
		if ( $invoice ) {
			$parts = explode( '.', $invoice );
			return $parts[0];
		} else {
			return '';
		}
	}

	/**
	 * @param array $row
	 * @param string $info
	 * @param bool $isChargeBack
	 * @return string|null
	 */
	private function getPayerInfo( array $row, string $info, bool $isChargeBack = false ): ?string {
		if ( !$isChargeBack && $row['paymentMethodSnapshot'] ) {
			$payerBlock = $row['paymentMethodSnapshot'];
		} elseif ( $isChargeBack && $row['transaction']['paymentMethodSnapshot'] ) {
			$payerBlock = $row['transaction']['paymentMethodSnapshot'];
		} else {
			return null;
		}
		// todo: figure out where is the venmo username should store other than firstname
		if ( $info == 'firstName' && isset( $payerBlock['username'] ) ) {
			return $payerBlock['username'];
		}
		if ( !empty( $payerBlock ) && isset( $payerBlock['payer'] ) ) {
			return $payerBlock['payer'][$info];
		}
		return null;
	}

	/**
	 * @param array $data
	 * @param string $type
	 * normalize transactions result from graphql to make the data more readable
	 */
	private function normalizeTransactions( array $data, string $type ) {
		$context = $this->isConsolidated() ? 'consolidated' : $type;
		if ( !isset( $this->fileData[$context] ) ) {
			$this->fileData[$context] = [];
		}
		$logRaw = $this->getOption( 'raw' );
		$outputRaw = $this->getOption( 'output-raw' );
		if ( $type === 'donation' || $type === 'refund' ) {
			foreach ( $data as $d ) {
				if ( $logRaw ) {
					Logger::info( "logging raw transaction " . json_encode( $d ) );
				}
				if ( !isset( $d['node'] ) ) {
					Logger::info( "no results found of type " . $type );
					continue;
				}
				if ( $outputRaw ) {
					$this->fileData[$context][] = $d['node'];
					continue;
				}
				$row = $d['node'];
				$msg                             = [];
				$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $row['orderId'] );
				$msg['invoice_id']               = $row['orderId'];
				$msg['payment_method']           = isset( $row['paymentMethodSnapshot']['payer'] ) ? 'paypal' : 'venmo';
				$msg['currency']                 = $row['amount']['currencyCode'];
				$msg['email']                    = $this->getPayerInfo( $row, 'email' );
				$msg['phone']                    = $this->getPayerInfo( $row, 'phone' );
				$msg['first_name']               = $this->getPayerInfo( $row, 'firstName' );
				$msg['last_name']                = $this->getPayerInfo( $row, 'lastName' );
				$msg['gross']                    = $row['amount']['value'];
				$msg['date']                     = $row['createdAt'];
				if ( $type === "donation" ) {
					$msg['gateway_txn_id'] = $row['id'];
				} else {
					$msg['gateway_parent_id'] = $row['refundedTransaction']['id'];
					$msg['gateway_refund_id'] = $row['id'];
					$msg['type']              = 'refund';
				}
				$this->fileData[$context][] = $msg;
			}
		}
		if ( $type === 'chargeback' ) {
			foreach ( $data as $d ) {
				if ( $logRaw ) {
					Logger::info( "logging raw transaction " . json_encode( $d ) );
				}
				if ( !isset( $d['node'] ) ) {
					Logger::info( "no results found of type " . $type );
					continue;
				}
				if ( $outputRaw ) {
					$this->fileData[$context][] = $d['node'];
					continue;
				}
				$row = $d['node'];
				$msg = [];
				// todo: find out what will dispute (chargeback) report looks like: need real case to see if really referenceNumber
				$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $row['transaction']['purchaseOrderNumber'] );
				$msg['invoice_id']               = $row['transaction']['purchaseOrderNumber'];
				$msg['payment_method']           = isset( $row['transaction']['paymentMethodSnapshot']['payer'] ) ? 'paypal' : 'venmo';
				$msg['gross']                    = $row['amountWon']['value'];
				$msg['currency']                 = $row['amountWon']['currencyCode'];
				$msg['email']                    = $this->getPayerInfo( $row, 'email', true );
				$msg['phone']                    = $this->getPayerInfo( $row, 'phone', true );
				$msg['first_name']               = $this->getPayerInfo( $row, 'firstName', true );
				$msg['last_name']                = $this->getPayerInfo( $row, 'lastName', true );
				$msg['gateway_txn_id']           = $row['id'];
				$msg['date']                     = $row['receivedDate'];
				$msg['type']                     = 'chargeback';
				$this->fileData[$context][] = $msg;
			}
		}
	}

	/**
	 * Get the date field to search by.
	 *
	 * @return string
	 */
	public function getDateField(): string {
		$dateField = $this->getOption( 'date-type' );
		if ( $dateField === 'created' ) {
			return 'createdAt';
		}
		if ( $dateField === 'disbursement' ) {
			return 'disbursementDate';
		}
		return 'receivedDate';
	}

	/**
	 * @param string $greaterThan
	 * @return array[]
	 */
	public function getInput( string $greaterThan ): array {
		$dateField = $this->getOption( 'date-type' );
		if ( $dateField === 'disbursement' ) {
			// The sum of disbursements adds up to the batch sum...
			$startDate = $this->getOption( 'start-date' );
			$endDate = $this->getOption( 'end-date' ) ?: $startDate;
			return [
				'disbursementDate' => [
					'greaterThanOrEqualTo' => gmdate( 'Y-m-d', strtotime( $startDate ) ),
					'lessThanOrEqualTo' => gmdate( 'Y-m-d', strtotime( $endDate ) ),
				]
			];
		}
		if ( $dateField === 'settled' ) {
			// This seems to work less well.
			return [
				'statusTransition' => [
					'settledAt' => [
						'greaterThanOrEqualTo' => $greaterThan,
						'lessThan' => $this->now,
					],
				],
			];
		}

		return [ $this->getDateField() => [ "greaterThanOrEqualTo" => $greaterThan, "lessThanOrEqualTo" => $this->now ] ];
	}

	private function isDisbursementReport(): bool {
		return $this->getOption( 'report-type' ) === 'disbursement';
	}

	/**
	 * @return bool
	 */
	private function isRunDonationSettlementReport(): bool {
		$type = $this->getOption( 'type' );
		return !$type || $type === 'all' || $type === 'donation';
	}

	/**
	 * @return bool
	 */
	private function isRunRefundReport(): bool {
		$type = $this->getOption( 'type' );
		return !$type || $type === 'all' || $type === 'refund';
	}

	/**
	 * @return bool
	 */
	private function isRunChargebackReport(): bool {
		$type = $this->getOption( 'type' );
		return !$type || $type === 'all' || $type === 'chargeback';
	}

	/**
	 * @param string $context
	 * @return resource
	 */
	private function getFile( string $context = '' ) {
		if ( $this->isConsolidated() ) {
			$context = 'consolidated';
		}
		if ( !isset( $this->files[$context] ) ) {
			// Put disbursement in the title if the disbursement option is used because
			// we will calculate totals if it is present.
			$type = $this->isDisbursementReport() ? 'disbursement' : 'batch';
			$pathPrefix = $this->getOption( 'output-raw' ) ? '/raw_' . $type . '_report_' : "/settlement_' . $type . '_report_";
			$path = $this->getOption( 'path' );
			if ( $context ) {
				$pathPrefix .= $context . '_';
			}
			// Start and end but end first for recency sorting.
			$mainFile = $path . $pathPrefix . gmdate( 'Y-m-d', strtotime( $this->getStartDate() ) ) . '_' . gmdate( 'Y-m-d', strtotime( $this->getEndDate() ) ) . ".json";
			$this->files[$context] = fopen( $mainFile, "w" ) or die( "Unable to open file!" );
		}
		return $this->files[$context];
	}

	/**
	 * @return string
	 */
	public function getStartDate(): string {
		$hrs = $this->getOption( 'hours' );
		$startDate = $this->getOption( 'start-date' );
		if ( $startDate ) {
			$greaterThan = date( 'c', strtotime( $startDate ) );
		} else {
			$greaterThan = date( 'c', strtotime( "-$hrs hours" ) );
		}
		return $greaterThan;
	}

	private function getDayBeforeStartDate(): string {
		return date( 'c', strtotime( '-1 day', strtotime( $this->getStartDate() ) ) );
	}

	/**
	 * @return string
	 */
	private function getEndDate(): string {
		$endDate = $this->getOption( 'end-date' );
		if ( $endDate ) {
			$endDate = gmdate( 'Y-m-d', strtotime( $endDate ) );
		} elseif ( $this->getOption( 'date-type' ) === 'disbursement' ) {
			// When disbursements are requested and only a single day is provided
			// then we treat this as a single day request.
			$endDate = $this->getOption( 'start-date' );
		} else {
			$endDate = substr( $this->now, 0, 10 );
		}
		return $endDate;
	}

	private function getDayBeforeEndDate(): string {
		return date( 'c', strtotime( '-1 day', strtotime( $this->getEndDate() ) ) );
	}

	/**
	 * @param mixed $context
	 * @return void
	 */
	public function writeToFile( string $context ): void {
		$file = $this->getFile( $context );
		foreach ( $this->fileData[$context] as $item ) {
			// Write each line individually so they wind up on separate lines - helpful when grepping
			// as otherwise the 'found line' is the whole file.
			fwrite( $file, json_encode( $item, JSON_UNESCAPED_SLASHES ) . "\n" );
		}
	}

	/**
	 * @return bool
	 */
	public function isConsolidated(): bool {
		return (bool)$this->getOption( 'consolidate' );
	}
}

$maintClass = SearchTransactions::class;

require RUN_MAINTENANCE_IF_MAIN;
