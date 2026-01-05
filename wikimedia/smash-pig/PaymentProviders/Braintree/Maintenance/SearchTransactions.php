<?php

namespace SmashPig\PaymentProviders\Braintree\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class SearchTransactions extends MaintenanceBase {

	protected array $fileData;

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
		$this->desiredOptions['config-node']['default'] = 'braintree';
	}

	/**
	 * Do the actual work of the script.
	 * @return void
	 */
	public function execute(): void {
		$hrs = $this->getOption( 'hours' );
		$startDate = $this->getOption( 'start-date' );
		$endDate = $this->getOption( 'end-date' );
		$type = $this->getOption( 'type' );
		$path = $this->getOption( 'path' );
		$outputRaw = $this->getOption( 'output-raw' );
		$now = date( 'c' );
		if ( $startDate ) {
			$greaterThan = date( 'c', strtotime( $startDate ) );
		} else {
			$greaterThan = date( 'c', strtotime( "-$hrs hours" ) );
		}
		$greaterThanDate = substr( $greaterThan, 0, 10 );
		if ( $endDate ) {
			$endDate = date( 'c', strtotime( $endDate ) );
		} else {
			$endDate = substr( $now, 0, 10 );
		}
		// get yesterday's or how many hrs from now's transaction, refunds and disputes
		Logger::info( "Get $type report from $greaterThanDate to $endDate\n" );
		if ( is_dir( $path ) ) {
			$provider = PaymentProviderFactory::getProviderForMethod( 'search' );

			$input = $this->getInput( $greaterThan, $now );
			$after = null;
			$pathPrefix = $outputRaw ? '/raw_batch_report_' : "/settlement_batch_report_";
			if ( $this->isRunDonationSettlementReport() ) {
				$response = $this->normalizeTransactions( $provider->searchTransactions( $input, $after ), 'donation' );
				$transactions = fopen( $path . $pathPrefix . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $transactions, $response );
				fclose( $transactions );
			}
			if ( $this->isRunRefundReport() ) {
				$refundResponse = $this->normalizeTransactions( $provider->searchRefunds( $input, $after ), 'refund' );
				$refunds = fopen( $path . $pathPrefix . "refund_" . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $refunds, $refundResponse );
				fclose( $refunds );
			}
			if ( $this->isRunChargebackReport() ) {
				// @todo - this would be disputes. The input date field is not respected because it's unclear we call this &
				// if we do which date field to use.
				$disputeInput = [ "receivedDate" => [ "greaterThanOrEqualTo" => $greaterThanDate, "lessThanOrEqualTo" => $endDate ] ];
				$disputeResponse = $this->normalizeTransactions( $provider->searchDisputes( $disputeInput, $after ), 'chargeback' );
				$disputes = fopen( $path . $pathPrefix . "dispute_" . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $disputes, $disputeResponse );
				fclose( $disputes );
			}
		} else {
			echo "incoming dir is not exist\n";
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
	 * @return string
	 */
	private function normalizeTransactions( array $data, string $type ): string {
		$this->fileData = [];
		$logRaw = $this->getOption( 'raw' );
		$outputRaw = $this->getOption( 'output-raw' );
		if ( $type === 'donation' || $type === 'refund' ) {
			foreach ( $data as $d ) {
				if ( $logRaw ) {
					Logger::info( "logging raw transaction " . json_encode( $d ) );
				}
				if ( $outputRaw ) {
					$this->fileData[] = $d['node'];
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
				$this->fileData[] = $msg;
			}
		}
		if ( $type === 'chargeback' ) {
			foreach ( $data as $d ) {
				if ( $logRaw ) {
					Logger::info( "logging raw transaction " . json_encode( $d ) );
				}
				if ( $outputRaw ) {
					$this->fileData[] = $d['node'];
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
				$this->fileData[] = $msg;
			}
		}
		return json_encode( $this->fileData ) . "\n";
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
	 * @param string $now
	 * @return array[]
	 */
	public function getInput( string $greaterThan, string $now ): array {
		$dateField = $this->getOption( 'date-type' );
		if ( $dateField === 'disbursement' ) {
			// The sum of disbursements adds up to the batch sum...
			$startDate = $this->getOption( 'start-date' );
			$endDate = $this->getOption( 'end-date' ) ?: $startDate;
			return [
				'disbursementDate' => [
					'greaterThanOrEqualTo' => date( 'Y-m-d', strtotime( $startDate ) ),
					'lessThanOrEqualTo' => date( 'Y-m-d', strtotime( $endDate ) ),
				]
			];
		}
		if ( $dateField === 'settled' ) {
			// This seems to work less well.
			return [
				'statusTransition' => [
					'settledAt' => [
						'greaterThanOrEqualTo' => $greaterThan,
						'lessThan' => $now
					],
				]
			];
		}

		$input = [ $this->getDateField() => [ "greaterThanOrEqualTo" => $greaterThan, "lessThanOrEqualTo" => $now ] ];
		return $input;
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
}

$maintClass = SearchTransactions::class;

require RUN_MAINTENANCE_IF_MAIN;
