<?php
namespace SmashPig\PaymentProviders\Braintree\Test;

use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Braintree\Audit\BraintreeAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Braintree audit file processor functions
 *
 * @group Audit
 * @group Braintree
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'date' => 1656383927,
			'gross' => '3.33',
			'contribution_tracking_id' => '20',
			'currency' => 'USD',
			'email' => 'fr-tech+donor@wikimedia.org',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'invoice_id' => '20.1',
			'phone' => null,
			'first_name' => 'f',
			'last_name' => 'doner',
			'payment_method' => 'paypal',
			'audit_file_gateway' => 'braintree',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse paypal donation correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1690227624,
			'gross' => '10.00',
			'contribution_tracking_id' => '68',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fZ3p5MnMwbjk',
			'invoice_id' => '68.1',
			'phone' => null,
			'first_name' => 'Ann',
			'last_name' => 'Fan',
			'payment_method' => 'venmo',
		];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse venmo donation correctly' );
	}

	public function testProcessRawDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_settlement_batch_report.json' );
		$this->assertCount( 3, $output, 'Should have found two donations' );
		$expected = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-21T22:58:37.000000Z' ),
			'gross' => '3.10',
			'original_total_amount' => '3.10',
			'settled_net_amount' => '3.10',
			'settled_total_amount' => '3.10',
			'settled_fee_amount' => '0',
			'contribution_tracking_id' => '24315',
			'original_currency' => 'USD',
			'settled_currency' => 'USD',
			'exchange_rate' => 1,
			'currency' => 'USD',
			'settlement_batch_reference' => '20251222',
			'settled_date' => strtotime( '2025-12-22 UTC' ),
			'invoice_id' => '24315.1',
			'phone' => null,
			'email' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'external_identifier' => 'xyz',
			'gateway_txn_id' => 'abcde',
			'full_name' => 'Donald Duck',
		];
		$this->assertEquals( $expected, $output[0], 'Did not parse paypal donation correctly' );
	}

	/**
	 * Normal donation
	 */
	public function testProcessOrchestratorDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_gravy_2022-06-27.json' );
		$this->assertCount( 1, $output, 'Should have found two donations' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'braintree',
			'backend_processor' => 'braintree',
			'backend_processor_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'payment_orchestrator_reconciliation_id' => '4dKvU4tsIv5DZRxK4jYbib',
			'date' => 1656383927,
			'gross' => '3.33',
			'currency' => 'USD',
			'email' => 'fr-tech+donor@wikimedia.org',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'invoice_id' => '4dKvU4tsIv5DZRxK4jYbib',
			'phone' => null,
			'first_name' => 'f',
			'last_name' => 'donor',
			'payment_method' => 'venmo',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse paypal donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_refund_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two refund donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1656390820,
			'gross' => '10.00',
			'contribution_tracking_id' => '34',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_parent_id' => 'dHJhbnNhY3Rpb25fMTYxZXdrMjk',
			'gateway_refund_id' => 'cmVmdW5kXzR6MXlyZ3o1',
			'invoice_id' => '34.1',
			'phone' => null,
			'first_name' => 'wenjun',
			'last_name' => 'fan',
			'payment_method' => 'paypal',
			'type' => 'refund',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse paypal refund correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1690485762,
			'gross' => '5.00',
			'contribution_tracking_id' => '61',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_parent_id' => 'dHJhbnNhY3Rpb25fY2EyMWdnNjk',
			'gateway_refund_id' => 'cmVmdW5kX2V5NWdnNjJl',
			'invoice_id' => '61.1',
			'phone' => null,
			'first_name' => 'Ann',
			'last_name' => 'Fan',
			'payment_method' => 'venmo',
			'type' => 'refund',
		];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse venmo refund correctly' );
	}

	/**
	 * Process raw refund
	 */
	public function testProcessRawRefund(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_refund.json' );
		$this->assertCount( 2, $output, 'Should have found two refunds' );
		$expected = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-19T19:26:35.000000Z UTC' ),
			'gross' => '52.00',
			'original_total_amount' => -52.0,
			'settled_net_amount' => -52.0,
			'settled_total_amount' => -52.0,
			'contribution_tracking_id' => '2402',
			'currency' => 'USD',
			'email' => null,
			'gateway_parent_id' => 'dHJh',
			'gateway_refund_id' => 'cmVmd',
			'invoice_id' => '2402.2',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'refund',
			'original_currency' => 'USD',
			'external_identifier' => 'J',
			'settled_date' => strtotime( '2025-12-22 UTC' ),
			'settlement_batch_reference' => '20251222',
			'settled_fee_amount' => 0,
			'exchange_rate' => '1',
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expected, $output[0], 'Did not parse refund correctly' );
	}

	/**
	 * Process raw refund
	 */
	public function testProcessRawGravyRefund(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_gravy_refund.json' );
		$this->assertCount( 1, $output, 'Should have found two refunds' );
		$expected = [
			'gateway' => 'gravy',
			'backend_processor' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-23T20:11:52.000000Z' ),
			'gross' => '1.03',
			'original_total_amount' => -1.03,
			'settled_net_amount' => -1.03,
			'settled_total_amount' => -1.03,
			'currency' => 'USD',
			'email' => null,
			'gateway_parent_id' => 'f8ee36ec-8e6a-490e-a9e8-6398e3e5e760',
			'gateway_refund_id' => 'cmVmdW5kX2g3OWY5Yzdo',
			'backend_processor_parent_id' => 'dHJhbnNhY3Rpb25fMHRjYzJ5cmo',
			'backend_processor_refund_id' => 'cmVmdW5kX2g3OWY5Yzdo',
			'invoice_id' => '7ZixbnFwSdg8h4IjcDPdTs',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'refund',
			'original_currency' => 'USD',
			'external_identifier' => 'Christine-Train',
			'settled_date' => strtotime( '2025-12-24 UTC' ),
			'settlement_batch_reference' => '20251224',
			'settled_fee_amount' => 0,
			'exchange_rate' => '1',
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expected, $output[0], 'Did not parse refund correctly' );
	}

	/**
	 * And a dispute
	 */
	public function testProcessDispute(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_dispute_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two dispute donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1656381367,
			'gross' => '3.33',
			'contribution_tracking_id' => '17',
			'currency' => 'USD',
			'email' => 'fr-tech+donor@wikimedia.org',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fa2F4eG1ycjE',
			'invoice_id' => '17.1',
			'phone' => null,
			'first_name' => 'f',
			'last_name' => 'doner',
			'payment_method' => 'paypal',
			'type' => 'chargeback',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse dispute paypal correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1690485762,
			'gross' => '5.00',
			'contribution_tracking_id' => '61',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fY2EyMWdnNjk',
			'invoice_id' => '61.1',
			'phone' => null,
			'first_name' => 'Ann',
			'last_name' => 'Fan',
			'payment_method' => 'venmo',
			'type' => 'chargeback',
		];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse dispute venmo correctly' );
	}

	/**
	 * And a dispute
	 */
	public function testProcessRawDispute(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_dispute.json' );
		$this->assertCount( 2, $output, 'Should have found two disputes that are resolved, others ignored' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-21 UTC' ),
			'gross' => '5.35',
			'original_total_amount' => '-5.35',
			'settled_net_amount' => '-5.35',
			'settled_total_amount' => '-5.35',
			'contribution_tracking_id' => '2387',
			'currency' => 'USD',
			'email' => null,
			'gateway_refund_id' => 'ZGlzcH',
			'backend_processor_reversal_id' => 'ZGlzcH',
			'invoice_id' => '2387.3',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'chargeback',
			'gateway_parent_id' => 'dHJhb',
			'original_currency' => 'USD',
			'external_identifier' => 'D',
			'settled_date' => strtotime( '2025-12-21 UTC' ),
			'settlement_batch_reference' => '20251221_ch',
			'settled_fee_amount' => 0,
			'exchange_rate' => 1,
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse dispute correctly' );
	}

	/**
	 * Process a dispute where the format is nd_json and there is only 1 row.
	 *
	 * For transitional reasons we handle full json and nd_json - in the latter
	 * case every row is a separate json object but the file itself is not valid json.
	 */
	public function testProcessRawDisputeSingleRowNDJSON(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_dispute_single_nd_json.json' );
		$this->assertCount( 1, $output, 'Should have found two disputes that are resolved, others ignored' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-21 UTC' ),
			'gross' => '5.35',
			'original_total_amount' => '-5.35',
			'settled_net_amount' => '-5.35',
			'settled_total_amount' => '-5.35',
			'contribution_tracking_id' => '2387',
			'currency' => 'USD',
			'email' => null,
			'gateway_refund_id' => 'ZGlzcH',
			'backend_processor_reversal_id' => 'ZGlzcH',
			'invoice_id' => '2387.3',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'chargeback',
			'gateway_parent_id' => 'dHJhb',
			'original_currency' => 'USD',
			'external_identifier' => 'D',
			'settled_date' => strtotime( '2025-12-22 UTC' ),
			'settlement_batch_reference' => '20251222_ch',
			'settled_fee_amount' => 0,
			'exchange_rate' => 1,
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse dispute correctly' );
	}

	/**
	 * Parse a LOST chargeback with statusHistory but no settlement date
	 */
	public function testProcessUnSettledDispute(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargeback_lost.json' );
		$actual = $output[0];
		$this->assertEquals( UtcDate::getUtcTimestamp( '2026-01-27' ), $actual['date'], 'Did not parse dispute history' );
		$this->assertArrayNotHasKey( 'settled_date', $actual );
	}

	public function reversalDisputeProvider(): array {
		return [
			'won top-level, disbursement on expired history event => reversal emitted' => [
				'row' => [
					'id' => 'ZGlzcHV0ZV81a3J6NDVyemN4cTh2NGt5',
					'legacyId' => '5krz45rzcxq8v4ky',
					'type' => 'CHARGEBACK',
					'caseNumber' => 'VM-R-CXX-604348562',
					'createdAt' => '2025-11-29T07:18:06.000000Z',
					'referenceNumber' => null,
					'receivedDate' => '2025-11-29',
					'status' => 'WON',
					'replyByDate' => '2025-12-08',
					'transaction' => [
						'purchaseOrderNumber' => null,
						'id' => 'dHJhbnNhY3Rpb25faGd2cmNxYnM',
						'legacyId' => 'hgvrcqbs',
						'status' => 'SETTLED',
						'orderId' => '233999167.5',
						'createdAt' => '2025-11-14T11:34:46.000000Z',
						'amount' => [
							'value' => '3.10',
							'currencyCode' => 'USD',
						],
						'paymentMethod' => null,
						'paymentMethodSnapshot' => [
							'username' => 'ZR',
							'venmoUserId' => '3027',
						],
					],
					'amountDisputed' => [
						'value' => '3.10',
						'currencyCode' => 'USD',
					],
					'amountWon' => [
						'value' => '3.10',
						'currencyCode' => 'USD',
					],
					'statusHistory' => [
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2026-02-23T18:01:52.000000Z',
							'disbursementDate' => null,
							'status' => 'WON',
							'effectiveDate' => '2026-02-23',
						],
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2025-12-08T08:15:21.000000Z',
							'disbursementDate' => '2026-02-24',
							'status' => 'EXPIRED',
							'effectiveDate' => '2025-12-08',
						],
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2025-11-29T07:18:06.000000Z',
							'disbursementDate' => null,
							'status' => 'OPEN',
							'effectiveDate' => '2025-11-29',
						],
					],
				],
				'expectedType' => 'chargeback_reversal',
				'expectedGross' => '3.10',
				'expectedCurrency' => 'USD',
				'expectedBatchRef' => '20260224_ch',
			],

			'won top-level, no disbursement anywhere => no reversal emitted' => [
				'row' => [
					'id' => 'ZGlzcHV0ZV80d3R3ZGd3ZDlwcHY0Y3Fm',
					'legacyId' => '4wtwdgwd9ppv4cqf',
					'type' => 'CHARGEBACK',
					'caseNumber' => 'VM-R-XKF-609365404',
					'createdAt' => '2025-12-31T02:04:04.000000Z',
					'referenceNumber' => null,
					'receivedDate' => '2025-12-31',
					'status' => 'WON',
					'replyByDate' => '2026-01-08',
					'transaction' => [
						'purchaseOrderNumber' => null,
						'id' => 'dHJhbnNhY3Rpb25fbTNndDJnNjQ',
						'legacyId' => 'm3gt2g64',
						'status' => 'SETTLED',
						'orderId' => '236192290.4',
						'createdAt' => '2025-12-19T09:36:55.000000Z',
						'amount' => [
							'value' => '3.10',
							'currencyCode' => 'USD',
						],
						'paymentMethod' => null,
						'paymentMethodSnapshot' => [
							'username' => 'P-A',
							'venmoUserId' => '2820',
						],
					],
					'amountDisputed' => [
						'value' => '3.10',
						'currencyCode' => 'USD',
					],
					'amountWon' => [
						'value' => '3.10',
						'currencyCode' => 'USD',
					],
					'statusHistory' => [
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2026-01-27T18:03:24.000000Z',
							'disbursementDate' => null,
							'status' => 'WON',
							'effectiveDate' => '2026-01-27',
						],
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2026-01-08T08:13:49.000000Z',
							'disbursementDate' => null,
							'status' => 'EXPIRED',
							'effectiveDate' => '2026-01-08',
						],
					],
				],
				'expectedType' => null,
				'expectedGross' => null,
				'expectedCurrency' => null,
				'expectedBatchRef' => null,
			],

			'won top-level, disbursement exists but amountWon is zero => no reversal emitted' => [
				'row' => [
					'id' => 'ZGlzcHV0ZV9mczN2cG00aDV4OXE1Znpt',
					'legacyId' => 'fs3vpm4h5x9q5fzm',
					'type' => 'CHARGEBACK',
					'caseNumber' => 'VM-R-KGR-611398929',
					'createdAt' => '2026-01-13T10:04:37.000000Z',
					'referenceNumber' => null,
					'receivedDate' => '2026-01-13',
					'status' => 'WON',
					'replyByDate' => '2026-01-22',
					'transaction' => [
						'purchaseOrderNumber' => null,
						'id' => 'dHJhbnNhY3Rpb25fNXJxZGVyaDQ',
						'legacyId' => '5rqderh4',
						'status' => 'SETTLED',
						'orderId' => '244398411.1',
						'createdAt' => '2026-01-04T21:47:03.000000Z',
						'amount' => [
							'value' => '10.40',
							'currencyCode' => 'USD',
						],
						'paymentMethod' => null,
						'paymentMethodSnapshot' => [
							'username' => 'SK',
							'venmoUserId' => '44049',
						],
					],
					'amountDisputed' => [
						'value' => '10.40',
						'currencyCode' => 'USD',
					],
					'amountWon' => [
						'value' => '0.00',
						'currencyCode' => 'USD',
					],
					'statusHistory' => [
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2026-02-19T10:03:00.000000Z',
							'disbursementDate' => null,
							'status' => 'WON',
							'effectiveDate' => '2026-02-19',
						],
						[
							'__typename' => 'DisputeStatusEvent',
							'timestamp' => '2026-01-22T08:14:29.000000Z',
							'disbursementDate' => '2026-01-26',
							'status' => 'EXPIRED',
							'effectiveDate' => '2026-01-22',
						],
					],
				],
				'expectedType' => null,
				'expectedGross' => null,
				'expectedCurrency' => null,
				'expectedBatchRef' => null,
			],
		];
	}

	/**
	 * @dataProvider reversalDisputeProvider
	 */
	public function testRawDisputeReversalDetection(
		array $row,
		?string $expectedType,
		?string $expectedGross,
		?string $expectedCurrency,
		?string $expectedBatchRef
	): void {
		$audit = new BraintreeAudit();

		$this->ingestRawLine( $audit, $row );

		$fileData = $this->readProperty( $audit, 'fileData' );

		if ( $expectedType === null ) {
			$this->assertNull( $fileData );
			return;
		}

		$this->assertCount( 1, $fileData );
		$message = $fileData[0];

		$this->assertSame( $expectedType, $message['type'] );
		$this->assertSame( $expectedGross, $message['gross'] );
		$this->assertSame( $expectedCurrency, $message['currency'] );
		$this->assertSame( $expectedBatchRef, $message['settlement_batch_reference'] );
	}

	private function ingestRawLine( BraintreeAudit $audit, array $row ): void {
		// Replace this with the real public entrypoint in your parser.
		// Examples might be parseLine, addRow, ingestLine, processLine, etc.
		$method = new \ReflectionMethod( $audit, 'parseLine' );
		$method->invoke( $audit, $row, true );
	}

	private function readProperty( object $object, string $property ) {
		$reflection = new \ReflectionProperty( $object, $property );
		return $reflection->getValue( $object );
	}
}
