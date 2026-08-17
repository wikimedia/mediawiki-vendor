<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Chariot\PendingDepositTracker;

/**
 * Tracks deposits whose donation details are not yet available from Chariot.
 *
 * When GetReport encounters a settled deposit that cannot yet be reconciled
 * because its donations are unavailable, the deposit is recorded as pending.
 * Subsequent runs retry any pending deposits until they are successfully
 * processed, at which point the pending record is removed.
 *
 * This occurs because some chariot providers appear to provide deposit information
 * to chariot before they provide donation information.
 *
 * The tracker persists its state as JSON files in the report directory.
 */
class PendingDepositTrackerTest extends TestCase {

	private string $directory;

	protected function setUp(): void {
		parent::setUp();

		$this->directory = sys_get_temp_dir() . '/smashpig-chariot-pending-' . uniqid( '', true );
		mkdir( $this->directory );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->directory . '/*' ) ?: [] as $file ) {
			unlink( $file );
		}
		rmdir( $this->directory );

		parent::tearDown();
	}

	public function testMarkPendingCreatesPayload(): void {
		$tracker = new PendingDepositTracker( $this->directory );

		$tracker->markPending( 'dep_123', 'No donations found for deposit yet' );

		$payload = $tracker->getPendingPayload( 'dep_123' );

		$this->assertSame( 'dep_123', $payload['deposit_id'] );
		$this->assertSame( 'No donations found for deposit yet', $payload['reason'] );
		$this->assertSame( 1, $payload['attempts'] );
		$this->assertNotEmpty( $payload['first_seen'] );
		$this->assertNotEmpty( $payload['last_attempt'] );
	}

	public function testMarkPendingUpdatesExistingPayload(): void {
		$tracker = new PendingDepositTracker( $this->directory );

		$tracker->markPending( 'dep_123', 'First reason' );
		$firstPayload = $tracker->getPendingPayload( 'dep_123' );

		$tracker->markPending( 'dep_123', 'Second reason' );
		$secondPayload = $tracker->getPendingPayload( 'dep_123' );

		$this->assertSame( $firstPayload['first_seen'], $secondPayload['first_seen'] );
		$this->assertSame( 'Second reason', $secondPayload['reason'] );
		$this->assertSame( 2, $secondPayload['attempts'] );
	}

	public function testGetPendingDepositIdsReturnsIdsFromFiles(): void {
		$tracker = new PendingDepositTracker( $this->directory );

		$tracker->markPending( 'dep_123', 'Pending' );
		$tracker->markPending( 'dep_456', 'Pending' );

		$ids = $tracker->getPendingDepositIds();
		sort( $ids );

		$this->assertSame( [ 'dep_123', 'dep_456' ], $ids );
	}

	public function testMarkResolvedRemovesFile(): void {
		$tracker = new PendingDepositTracker( $this->directory );

		$tracker->markPending( 'dep_123', 'Pending' );
		$tracker->markResolved( 'dep_123' );

		$this->assertNull( $tracker->getPendingPayload( 'dep_123' ) );
		$this->assertSame( [], $tracker->getPendingDepositIds() );
	}

	public function testDepositIdsAreSafeForFilenames(): void {
		$tracker = new PendingDepositTracker( $this->directory );

		$tracker->markPending( 'dep/123:abc', 'Pending' );

		$this->assertSame( [ 'dep/123:abc' ], $tracker->getPendingDepositIds() );
		$this->assertFileExists(
			$this->directory . '/pending-chariot-deposit-dep_123_abc.json'
		);
	}

	public function testMalformedPendingFilesAreIgnored(): void {
		file_put_contents(
			$this->directory . '/pending-chariot-deposit-bad.json',
			'not json'
		);

		$tracker = new PendingDepositTracker( $this->directory );

		$this->assertSame( [], $tracker->getPendingDepositIds() );
	}
}
