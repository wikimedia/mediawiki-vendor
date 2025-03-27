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
		$this->addOption( 'type', 'search what type of transactions (donation, refund, chargeback)', 'all', 't' );
		$this->addOption( 'path', 'location to store the reports', './drupal/sites/default/files/wmf_audit/braintree/incoming', 'p' );
		$this->desiredOptions['config-node']['default'] = 'braintree';
	}

	/**
	 * Do the actual work of the script.
	 * @return void
	 */
	public function execute() {
		$hrs = $this->getOption( 'hours' );
		$type = $this->getOption( 'type' );
		$path = $this->getOption( 'path' );
		$now = date( 'c' );
		$greaterThan = date( 'c', strtotime( "-$hrs hours" ) );
		$greaterThanDate = substr( $greaterThan, 0, 10 );
		$todayDate = substr( $now, 0, 10 );
		// get yesterday's or how many hrs from now's transaction, refunds and disputes
		Logger::info( "Get $type report from $greaterThanDate to $todayDate\n" );
		if ( is_dir( $path ) ) {
			$provider = PaymentProviderFactory::getProviderForMethod( 'search' );
			$input = [ "createdAt" => [ "greaterThanOrEqualTo" => $greaterThan, "lessThanOrEqualTo" => $now ], "status" => [ "is" => "SETTLED" ] ];
			$after = null;
			if ( $type !== 'chargeback' && $type !== 'refund' ) {
				$response = $this->normalizeTransactions( $provider->searchTransactions( $input, $after ), 'donation' );
				$transactions = fopen( $path . "/settlement_batch_report_" . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $transactions, $response );
				fclose( $transactions );
			}
			if ( $type !== 'donation' && $type !== 'chargeback' ) {
				$refundResponse = $this->normalizeTransactions( $provider->searchRefunds( $input, $after ), 'refund' );
				$refunds = fopen( $path . "/settlement_batch_report_refund_" . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $refunds, $refundResponse );
				fclose( $refunds );
			}
			if ( $type !== 'donation' && $type !== 'refund' ) {
				$disputeInput = [ "receivedDate" => [ "greaterThanOrEqualTo" => $greaterThanDate, "lessThanOrEqualTo" => $todayDate ] ];
				$disputeResponse = $this->normalizeTransactions( $provider->searchDisputes( $disputeInput, $after ), 'chargeback' );
				$disputes = fopen( $path . "/settlement_batch_report_dispute_" . $greaterThanDate . ".json", "w" ) or die( "Unable to open file!" );
				fwrite( $disputes, $disputeResponse );
				fclose( $disputes );
			}
		} else {
			echo "drupal incoming dir is not exist\n";
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
		if ( $type === 'donation' || $type === 'refund' ) {
			foreach ( $data as $d ) {
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
		return json_encode( $this->fileData );
	}

}

$maintClass = SearchTransactions::class;

require RUN_MAINTENANCE_IF_MAIN;
