<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use SmashPig\PaymentProviders\Chariot\ChariotObjectMetadata;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Chariot
 */
class ChariotObjectMetadataTest extends BaseSmashPigUnitTestCase {

	public function testImportantFieldsHaveMetadata(): void {
		$depositFields = ChariotObjectMetadata::getDepositFields();
		$donationFields = ChariotObjectMetadata::getDonationFields();

		$this->assertSame(
			ChariotObjectMetadata::STATUS_USED,
			$depositFields['transfer.amount']['status']
		);
		$this->assertSame(
			'Donor check number.',
			$depositFields['transfer.check_deposit.auxiliary_on_us']['note']
		);
		$this->assertSame(
			ChariotObjectMetadata::STATUS_USED,
			$donationFields['amount_net']['status']
		);
		$this->assertSame(
			ChariotObjectMetadata::STATUS_USED,
			$donationFields['properties.Gift Type']['status']
		);
	}

	public function testKnownPathsIncludeImpliedParentPaths(): void {
		$depositPaths = ChariotObjectMetadata::getKnownDepositPaths();

		$this->assertContains( 'transfer', $depositPaths );
		$this->assertContains( 'transfer.inbound_ach_transfer', $depositPaths );
		$this->assertContains(
			'transfer.inbound_ach_transfer.originator_company_name',
			$depositPaths
		);

		$donationPaths = ChariotObjectMetadata::getKnownDonationPaths();

		$this->assertContains( 'platform', $donationPaths );
		$this->assertContains( 'platform.metadata', $donationPaths );
		$this->assertContains( 'platform.metadata.Project', $donationPaths );
	}

}
