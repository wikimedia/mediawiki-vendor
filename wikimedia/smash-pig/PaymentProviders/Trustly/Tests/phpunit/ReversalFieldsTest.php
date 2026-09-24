<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Test;

require_once 'AuditTestBase.php';

/**
 * Verify how SettlementFileParser normalizes chargebacks and refunds.
 *
 * @group Trustly
 * @group Audit
 */
class ReversalFieldsTest extends AuditTestBase {

	private function findByType( array $rows, string $type ): array {
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? null ) === $type ) {
				return $row;
			}
		}
		$this->fail( "No row of type '$type' found in output" );
	}

	/**
	 * For non-reversal rows, getGatewayTxnId() returns the decoded UUID
	 * (Base62Helper::toUuid of original_merchant_reference). Chargebacks and
	 * refunds must follow the same contract instead of returning the raw
	 * Trustly transaction_id: downstream, AuditMessage::getContributionRecurID()
	 * compares this field against ContributionRecur.trxn_id, which is stored
	 * as the UUID - so a raw numeric id here can never match, silently
	 * defeating the recurring-series safeguard that's supposed to strip
	 * gateway_txn_id/payment_orchestrator_reconciliation_id in that case.
	 */
	public function testChargebackGatewayTxnIdIsTheDecodedUuidNotTheRawTrustlyId(): void {
		$output = $this->processFile( 'P11KFUN-3618-recurring-series-collision.csv' );
		$chargeback = $this->findByType( $output, 'chargeback' );

		$this->assertSame(
			$chargeback['gateway_parent_id'],
			$chargeback['gateway_txn_id'],
			'gateway_txn_id must be the decoded UUID, same as gateway_parent_id - not the raw ' .
			'backend_processor_txn_id ("9100000003")'
		);
		$this->assertNotSame( '9100000003', $chargeback['gateway_txn_id'] );
	}

	/**
	 * Chargebacks use trace_id, not transaction_id, for backend_processor_reversal_id.
	 */
	public function testChargebackRefundHasBackendProcessorReversal(): void {
		$output = $this->processFile( 'P11KFUN-3618-recurring-series-collision.csv' );
		$chargeback = $this->findByType( $output, 'chargeback' );
		$this->assertSame( 'T2', $chargeback['backend_processor_reversal_id'] );
	}

	/**
	 * ACH return codes other than R08/R10 (e.g. R03) are chargebacks too now -
	 * the positive (Sale) leg stays untyped since it settled before being
	 * reversed. Data taken from a real transaction (8202131071/8202130573).
	 */
	public function testUnhandledRCodeIsTypedAsChargeback(): void {
		$output = $this->processFile( 'P11KFUN-3618-r-code-reversal.csv' );

		$chargeback = $this->findByType( $output, 'chargeback' );
		$this->assertSame( 'gravy', $chargeback['gateway'] );
		$this->assertSame( '33800551113', $chargeback['backend_processor_reversal_id'] );
		$this->assertSame( $chargeback['gateway_parent_id'], $chargeback['gateway_txn_id'] );

		$saleLeg = null;
		foreach ( $output as $row ) {
			if ( !isset( $row['type'] ) && ( $row['backend_processor_txn_id'] ?? null ) === '8202131071' ) {
				$saleLeg = $row;
			}
		}
		$this->assertNotNull( $saleLeg, 'Sale leg should be untyped' );
		$this->assertSame( 10.4, $saleLeg['gross'] );
	}

	/**
	 * Test that an AC118 refund has a gateway of gravy.
	 *
	 * It may have a long (hashed) original_merchant_reference that is not a gravy id
	 * but still need to be matched with a gravy transaction. Seen in real data:
	 * transaction_id 8090501261 (P11KFUN-3618-20260208120000-20260209120000-0001of0001.csv)
	 * is a genuinely gravy capture with a normal short reference, but its own AC118 refund -
	 * transaction_id 8094565296, original_transaction_id 8090501261
	 * (P11KFUN-3618-20260216120000-20260217120000-0001of0001.csv) - carries a long 64-char
	 * hash reference instead.
	 */
	public function testRefundWithLongMerchantReferenceKeepsGravyGateway(): void {
		$output = $this->processFile( 'P11KFUN-3618-refund-long-merchant-reference.csv' );
		$refund = $this->findByType( $output, 'refund' );

		$this->assertSame( 'gravy', $refund['gateway'] );
		$this->assertSame( '9500000000', $refund['backend_processor_parent_id'] );
	}
}
