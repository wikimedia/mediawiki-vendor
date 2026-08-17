<?php

namespace SmashPig\PaymentProviders\Chariot\Maintenance;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Chariot\Api;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class SetProperty extends MaintenanceBase {

	private Api $api;
	private ProviderConfiguration $config;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'property', 'Chariot property name, e.g. "CRM status"', '', 'p' );
		$this->addOption( 'option', 'Chariot enum option name, e.g. "Synced in CRM"', '', 'o' );
		$this->addOption( 'value', 'Property value for text, boolean, date, or user properties', '', 'v' );
		$this->addFlag( 'empty', 'Unset/empty this property value', '' );
		$this->addOption( 'resource-type', 'Chariot resource type, e.g. donation or deposit', '' );
		$this->addOption( 'resource-id', 'Chariot resource ID to assign the property to', '' );
		$this->addOption( 'donation-id', 'Chariot donation ID to assign the property to', '', 'd' );
		$this->addOption( 'deposit-id', 'Chariot deposit ID to assign the property to', '' );
		$this->addOption(
			'donations-for-deposit-id',
			'Chariot deposit ID; assigns the property to all donations in that deposit',
			''
		);
		$this->addOption( 'limit', 'Optional maximum results per donations list call', '', 'l' );
		$this->desiredOptions['config-node']['default'] = 'chariot';
	}

	public function execute(): void {
		$this->config = Context::get()->getProviderConfiguration();
		$this->api = new Api();

		$target = $this->getTarget();
		$property = $this->api->getPropertyByName(
			$target['resource_type'],
			$this->requireOption( 'property' )
		);
		if ( $property === null ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to find Chariot property "%s" for resource type "%s"',
					$this->requireOption( 'property' ),
					$target['resource_type']
				)
			);
		}

		$option = $this->api->getPropertyOptionByName(
			$property,
			$this->requireOption( 'option' )
		);
		if ( $option === null ) {
			throw new \RuntimeException(
				sprintf(
					'Unable to find option "%s" for Chariot property "%s"',
					$this->requireOption( 'option' ),
					$this->requireOption( 'property' )
				)
			);
		}
		$value = $this->getPropertyValue( $property );
		Logger::info( 'Assigning Chariot property: ' . json_encode( [
				'property_id' => $property['id'],
				'resource_ids' => $target['resource_ids'],
				'value' => $value,
			], JSON_UNESCAPED_SLASHES ) );
		$result = $this->api->assignProperty(
			$property['id'],
			$target['resource_ids'],
			$value
		);

		$json = json_encode(
			[
				'resource_type' => $target['resource_type'],
				'resource_ids' => $target['resource_ids'],
				'property' => [
					'id' => $property['id'],
					'name' => $property['name'] ?? $this->requireOption( 'property' ),
				],
				'option' => [
					'id' => $option['id'],
					'name' => $option['name'] ?? $this->requireOption( 'option' ),
				],
				'result' => $result,
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
		);

		if ( $json === false ) {
			throw new \RuntimeException( 'Unable to encode SetProperty result as JSON' );
		}

		print $json . PHP_EOL;
	}

	private function getPropertyValue( array $property ): array {
		if ( $this->getOption( 'empty' ) ) {
			return [
				'type' => $property['property_type'],
				'empty' => true,
			];
		}

		$type = (string)( $property['property_type'] ?? '' );

		switch ( $type ) {
			case 'enum':
				$optionName = $this->requireOption( 'option' );
				$option = $this->api->getPropertyOptionByName( $property, $optionName );
				if ( $option === null ) {
					throw new \RuntimeException(
						sprintf(
							'Unable to find option "%s" for Chariot property "%s"',
							$optionName,
							$property['name'] ?? ''
						)
					);
				}
				return [
					'type' => 'enum',
					'enum_value_id' => $option['id'],
				];

			case 'text':
				return [
					'type' => 'text',
					'text_value' => $this->requireOption( 'value' ),
				];

			case 'boolean':
				return [
					'type' => 'boolean',
					'boolean_value' => $this->getBooleanValue(),
				];

			case 'date':
				return [
					'type' => 'date',
					'date_value' => $this->requireOption( 'value' ),
				];

			case 'user':
				return [
					'type' => 'user',
					'user_value_id' => $this->requireOption( 'value' ),
				];

			default:
				throw new \RuntimeException( 'Unsupported Chariot property type: ' . $type );
		}
	}

	private function getBooleanValue(): bool {
		$value = strtolower( $this->requireOption( 'value' ) );

		if ( in_array( $value, [ '1', 'true', 'yes', 'y' ], true ) ) {
			return true;
		}

		if ( in_array( $value, [ '0', 'false', 'no', 'n' ], true ) ) {
			return false;
		}

		throw new \InvalidArgumentException( 'Boolean --value must be one of true, false, yes, no, 1, or 0.' );
	}

	/**
	 * @return array{resource_type:string,resource_ids:string[]}
	 */
	private function getTarget(): array {
		$resourceType = trim( (string)$this->getOption( 'resource-type' ) );
		$resourceId = trim( (string)$this->getOption( 'resource-id' ) );
		$donationId = trim( (string)$this->getOption( 'donation-id' ) );
		$depositId = trim( (string)$this->getOption( 'deposit-id' ) );
		$donationsForDepositId = trim( (string)$this->getOption( 'donations-for-deposit-id' ) );

		$targetCount = (int)( $resourceId !== '' )
			+ (int)( $donationId !== '' )
			+ (int)( $depositId !== '' )
			+ (int)( $donationsForDepositId !== '' );

		if ( $targetCount !== 1 ) {
			throw new \InvalidArgumentException(
				'Pass exactly one of --resource-id, --donation-id, --deposit-id, or --donations-for-deposit-id.'
			);
		}

		if ( $donationId !== '' ) {
			return [
				'resource_type' => 'donation',
				'resource_ids' => [ $donationId ],
			];
		}

		if ( $depositId !== '' ) {
			return [
				'resource_type' => 'deposit',
				'resource_ids' => [ $depositId ],
			];
		}

		if ( $donationsForDepositId !== '' ) {
			return [
				'resource_type' => 'donation',
				'resource_ids' => $this->getDonationIdsForDeposit( $donationsForDepositId ),
			];
		}

		if ( $resourceType === '' ) {
			throw new \InvalidArgumentException( 'Pass --resource-type when using --resource-id.' );
		}

		return [
			'resource_type' => $resourceType,
			'resource_ids' => [ $resourceId ],
		];
	}

	/**
	 * @return string[]
	 */
	private function getDonationIdsForDeposit( string $depositId ): array {
		$result = $this->api->listDonations(
			array_filter( [
				'deposit_id' => $depositId,
				'limit' => $this->getLimitOption(),
			] )
		);

		$donationIds = [];
		foreach ( $result['results'] ?? [] as $donation ) {
			if ( is_array( $donation ) && !empty( $donation['id'] ) ) {
				$donationIds[] = (string)$donation['id'];
			}
		}

		if ( $donationIds === [] ) {
			throw new \RuntimeException( 'No donations found for deposit: ' . $depositId );
		}

		return $donationIds;
	}

	private function getLimitOption(): ?int {
		$value = trim( (string)$this->getOption( 'limit' ) );
		if ( $value === '' ) {
			return null;
		}

		$intValue = (int)$value;
		return $intValue > 0 ? $intValue : null;
	}

	private function requireOption( string $name ): string {
		$value = trim( (string)$this->getOption( $name ) );
		if ( $value === '' ) {
			throw new \InvalidArgumentException( sprintf( 'Missing required --%s option', $name ) );
		}
		return $value;
	}
}

$maintClass = SetProperty::class;
require RUN_MAINTENANCE_IF_MAIN;
