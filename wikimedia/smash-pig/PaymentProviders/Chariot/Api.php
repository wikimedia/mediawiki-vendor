<?php

namespace SmashPig\PaymentProviders\Chariot;

use SmashPig\Core\Context;
use SmashPig\Core\Http\OutboundRequest;

class Api {

	private const DEFAULT_API_BASE = 'https://api.givechariot.com';

	/**
	 * Process-local cache TTL in seconds.
	 */
	private const PROPERTY_CACHE_TTL = 3600;

	private string $apiKey;

	private string $apiBase;

	/**
	 * Cache shape:
	 * [
	 *   'deposit' => [
	 *     'expires' => 1234567890,
	 *     'map' => [ 'prop_x' => [ ...property definition... ] ]
	 *   ],
	 *   'donation' => [
	 *     'expires' => 1234567890,
	 *     'map' => [ 'prop_y' => [ ...property definition... ] ]
	 *   ],
	 * ]
	 */
	private static array $propertyMapCache = [];

	public function __construct() {
		$this->apiKey = Context::get()->getProviderConfiguration()->val( 'api_key' );
		$this->apiBase = Context::get()->getProviderConfiguration()->val( 'api_base_url' ) ?: self::DEFAULT_API_BASE;
	}

	/**
	 * List deposits.
	 *
	 * Docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/list?explorer=true
	 */
	public function listDeposits( array $filters = [] ): array {
		$query = [];
		foreach ( [ 'page_token', 'limit', 'settled_at.after', 'settled_at.before' ] as $key ) {
			if ( isset( $filters[$key] ) && $filters[$key] !== '' && $filters[$key] !== null ) {
				$query[$key] = $filters[$key];
			}
		}

		$response = $this->requestJson( 'GET', '/v1/deposits', $query );

		if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as &$deposit ) {
				if ( is_array( $deposit ) ) {
					$deposit = $this->normalizeResourceProperties( $deposit, 'deposit' );
				}
			}
			unset( $deposit );
		}

		return $response;
	}

	/**
	 * Get a single deposit.
	 *
	 * Docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/get
	 */
	public function getDeposit( string $depositId ): array {
		$deposit = $this->requestJson( 'GET', '/v1/deposits/' . rawurlencode( $depositId ) );
		return $this->normalizeResourceProperties( $deposit, 'deposit' );
	}

	/**
	 * List donations.
	 *
	 * Docs:
	 * https://docs.givechariot.com/api/donations/list
	 */
	public function listDonations( array $filters = [] ): array {
		$query = [];
		foreach ( [ 'deposit_id', 'payment_source_id', 'page_token', 'limit', 'created_at.after', 'created_at.before' ] as $key ) {
			if ( isset( $filters[$key] ) && $filters[$key] !== '' && $filters[$key] !== null ) {
				$query[$key] = $filters[$key];
			}
		}

		$response = $this->requestJson( 'GET', '/v1/donations', $query );

		if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as &$donation ) {
				if ( is_array( $donation ) ) {
					$donation = $this->normalizeResourceProperties( $donation, 'donation' );
				}
			}
			unset( $donation );
		}

		return $response;
	}

	/**
	 * Get a single donation.
	 *
	 * Docs:
	 * https://docs.givechariot.com/api/donations/get
	 */
	public function getDonation( string $donationId ): array {
		$donation = $this->requestJson( 'GET', '/v1/donations/' . rawurlencode( $donationId ) );
		return $this->normalizeResourceProperties( $donation, 'donation' );
	}

	/**
	 * List properties from Chariot.
	 *
	 * Docs:
	 * https://docs.givechariot.com/api/properties/list
	 *
	 * Supported query params:
	 * - page_token
	 * - limit
	 * - resource_type
	 */
	public function listProperties( array $filters = [] ): array {
		$query = [];
		foreach ( [ 'page_token', 'limit', 'resource_type' ] as $key ) {
			if ( isset( $filters[$key] ) && $filters[$key] !== '' && $filters[$key] !== null ) {
				$query[$key] = $filters[$key];
			}
		}

		return $this->requestJson( 'GET', '/v1/properties', $query );
	}

	/**
	 * Replace Chariot property payloads with:
	 * [
	 *   'Campaign' => 'Spring Appeal',
	 *   'Fund' => 'General Fund',
	 * ]
	 */
	private function normalizeResourceProperties( array $resource, string $resourceType ): array {
		if ( empty( $resource['properties'] ) || !is_array( $resource['properties'] ) ) {
			return $resource;
		}

		$propertyMap = $this->getCachedPropertyMap( $resourceType );
		$normalized = [];

		foreach ( $resource['properties'] as $assignedProperty ) {
			if ( !is_array( $assignedProperty ) ) {
				continue;
			}

			$propertyId = $assignedProperty['property_id'] ?? null;
			$valuePayload = $assignedProperty['value'] ?? null;

			$property = is_string( $propertyId ) ? ( $propertyMap[$propertyId] ?? null ) : null;
			$propertyName = is_array( $property ) ? ( $property['name'] ?? $propertyId ) : $propertyId;
			$value = $this->resolvePropertyValue( $valuePayload, $property );

			if ( $propertyName !== null ) {
				$normalized[$propertyName] = $value;
			}
		}

		$resource['properties'] = $normalized;
		return $resource;
	}

	/**
	 * Resolve a Chariot property value into a human-readable scalar/array.
	 *
	 * @param mixed $valuePayload
	 * @param array|null $property
	 * @return mixed
	 */
	private function resolvePropertyValue( $valuePayload, ?array $property ) {
		if ( !is_array( $valuePayload ) ) {
			return $valuePayload;
		}

		$type = $valuePayload['type'] ?? null;

		if (
			$type === 'enum' &&
			isset( $valuePayload['enum_value_id'] ) &&
			is_string( $valuePayload['enum_value_id'] )
		) {
			$enumId = $valuePayload['enum_value_id'];

			if ( !empty( $property['options'] ) && is_array( $property['options'] ) ) {
				foreach ( $property['options'] as $option ) {
					if (
						is_array( $option ) &&
						isset( $option['id'] ) &&
						$option['id'] === $enumId
					) {
						return $option['name'] ?? $enumId;
					}
				}
			}

			return $enumId;
		}

		foreach ( [ 'value', 'text', 'text_value', 'string_value', 'number_value', 'boolean_value', 'date_value', 'user_value_id' ] as $key ) {
			if ( array_key_exists( $key, $valuePayload ) ) {
				return $valuePayload[$key];
			}
		}

		$fallback = $valuePayload;
		unset( $fallback['type'] );

		if ( count( $fallback ) === 1 ) {
			return reset( $fallback );
		}

		return $fallback;
	}

	/**
	 * Get a cached map of property_id => property definition for one resource type.
	 *
	 * @return array<string,array>
	 */
	private function getCachedPropertyMap( string $resourceType ): array {
		$now = time();

		if ( isset( self::$propertyMapCache[$resourceType] ) ) {
			$cacheEntry = self::$propertyMapCache[$resourceType];
			if (
				isset( $cacheEntry['expires'] ) &&
				isset( $cacheEntry['map'] ) &&
				$cacheEntry['expires'] > $now
			) {
				return $cacheEntry['map'];
			}
		}

		$propertyMap = [];
		$pageToken = null;

		do {
			$query = [
				'resource_type' => $resourceType,
				'limit' => 100,
			];

			if ( $pageToken ) {
				$query['page_token'] = $pageToken;
			}

			$response = $this->listProperties( $query );
			$results = $response['results'] ?? [];

			if ( is_array( $results ) ) {
				foreach ( $results as $property ) {
					if (
						is_array( $property ) &&
						isset( $property['id'] ) &&
						is_string( $property['id'] )
					) {
						$propertyMap[$property['id']] = $property;
					}
				}
			}

			$pageToken = $response['next_page_token'] ?? null;
		} while ( $pageToken );

		self::$propertyMapCache[$resourceType] = [
			'expires' => $now + self::PROPERTY_CACHE_TTL,
			'map' => $propertyMap,
		];

		return $propertyMap;
	}

	protected function requestJson( string $method, string $path, array $query = [], ?array $body = null ): array {
		$response = $this->requestRaw( $method, $this->buildUrl( $path, $query ), $body );
		$result = json_decode( $response, true );

		if ( !is_array( $result ) ) {
			throw new \RuntimeException( 'Chariot API returned invalid JSON' );
		}

		if ( isset( $result['error'] ) ) {
			$error = is_string( $result['error'] ) ? $result['error'] : json_encode( $result['error'] );
			throw new \RuntimeException( 'Chariot API error: ' . $error );
		}

		return $result;
	}

	private function requestRaw( string $method, string $url, ?array $body = null ): string {
		$req = new OutboundRequest( $url, $method );
		$req->setHeader( 'Authorization', 'Bearer ' . $this->apiKey );
		$req->setHeader( 'User-Agent', 'SmashPig Chariot report downloader' );

		if ( $method !== 'GET' && $body ) {
			$req->setHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
			$req->setBody( $body );
		}

		$response = $req->execute();
		$statusCode = $response['status'];

		if ( $statusCode < 200 || $statusCode >= 300 ) {
			throw new \RuntimeException(
				sprintf( 'Chariot request to %s failed with HTTP %d: %s', $url, $statusCode, $response['body'] )
			);
		}

		return $response['body'];
	}

	private function buildUrl( string $path, array $query = [] ): string {
		$url = rtrim( $this->apiBase, '/' ) . '/' . ltrim( $path, '/' );
		if ( $query ) {
			$url .= '?' . http_build_query( $query );
		}
		return $url;
	}
}
