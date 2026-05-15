<?php

namespace SmashPig\PaymentProviders\Chariot\Tests;

use SmashPig\PaymentProviders\Chariot\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Chariot
 */
class ApiTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Api
	 */
	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfiguration = $this->setProviderConfiguration( 'chariot' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		$providerConfiguration->override( [
			'api_key' => 'test_chariot_api_key',
			'api_base_url' => 'https://api.givechariot.com',
		] );
		$this->api = new Api();
	}

	public function testListDepositsSendsSupportedFilters() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->callback( function ( $url ) {
					$parsed = parse_url( $url );
					$this->assertSame( '/v1/deposits', $parsed['path'] ?? '' );

					parse_str( $parsed['query'] ?? '', $query );
					$this->assertSame( '100', (string)( $query['limit'] ?? '' ) );
					$this->assertSame( 'page_token_123', (string)( $query['page_token'] ?? '' ) );
					$this->assertSame( '2026-03-01T00:00:00Z', (string)( $query['settled_at_after'] ?? '' ) );
					$this->assertSame( '2026-04-01T00:00:00Z', (string)( $query['settled_at_before'] ?? '' ) );
					$this->assertArrayNotHasKey( 'ignored_param', $query );

					return true;
				} ),
				'GET',
				$this->callback( function ( $headers ) {
					$this->assertSame( 'Bearer test_chariot_api_key', $headers['Authorization'] ?? '' );
					$this->assertSame( 'SmashPig Chariot report downloader', $headers['User-Agent'] ?? '' );
					return true;
				} ),
				null
			)
			->willReturn( [
				'body' => json_encode( [
					'results' => [],
					'next_page_token' => 'next_page_token_456',
				] ),
				'headers' => [],
				'status' => 200,
			] );

		$result = $this->api->listDeposits( [
			'limit' => 100,
			'page_token' => 'page_token_123',
			'settled_at.after' => '2026-03-01T00:00:00Z',
			'settled_at.before' => '2026-04-01T00:00:00Z',
			'ignored_param' => 'nope',
		] );

		$this->assertEquals(
			[
				'results' => [],
				'next_page_token' => 'next_page_token_456',
			],
			$result
		);
	}

	public function testGetDepositRequestsSingleDeposit() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->callback( function ( $url ) {
					$this->assertSame(
						'https://api.givechariot.com/v1/deposits/deposit_01abcxyz',
						$url
					);
					return true;
				} ),
				'GET',
				$this->callback( function ( $headers ) {
					$this->assertSame( 'Bearer test_chariot_api_key', $headers['Authorization'] ?? '' );
					return true;
				} ),
				null
			)
			->willReturn( [
				'body' => json_encode( [
					'id' => 'deposit_01abcxyz',
					'status' => 'complete',
				] ),
				'headers' => [],
				'status' => 200,
			] );

		$result = $this->api->getDeposit( 'deposit_01abcxyz' );

		$this->assertEquals(
			[
				'id' => 'deposit_01abcxyz',
				'status' => 'complete',
			],
			$result
		);
	}

	public function testListDonationsSendsSupportedFilters() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->callback( function ( $url ) {
					$parsed = parse_url( $url );
					$this->assertSame( '/v1/donations', $parsed['path'] ?? '' );

					parse_str( $parsed['query'] ?? '', $query );
					$this->assertSame( 'deposit_01abcxyz', (string)( $query['deposit_id'] ?? '' ) );
					$this->assertSame( 'payment_source_123', (string)( $query['payment_source_id'] ?? '' ) );
					$this->assertSame( '100', (string)( $query['limit'] ?? '' ) );
					$this->assertSame( 'page_token_123', (string)( $query['page_token'] ?? '' ) );
					$this->assertSame( '2026-03-01T00:00:00Z', (string)( $query['created_at_after'] ?? '' ) );
					$this->assertSame( '2026-04-01T00:00:00Z', (string)( $query['created_at_before'] ?? '' ) );
					$this->assertArrayNotHasKey( 'ignored_param', $query );

					return true;
				} ),
				'GET',
				$this->callback( function ( $headers ) {
					$this->assertSame( 'Bearer test_chariot_api_key', $headers['Authorization'] ?? '' );
					$this->assertSame( 'SmashPig Chariot report downloader', $headers['User-Agent'] ?? '' );
					return true;
				} ),
				null
			)
			->willReturn( [
				'body' => json_encode( [
					'results' => [],
				] ),
				'headers' => [],
				'status' => 200,
			] );

		$result = $this->api->listDonations( [
			'deposit_id' => 'deposit_01abcxyz',
			'payment_source_id' => 'payment_source_123',
			'limit' => 100,
			'page_token' => 'page_token_123',
			'created_at.after' => '2026-03-01T00:00:00Z',
			'created_at.before' => '2026-04-01T00:00:00Z',
			'ignored_param' => 'nope',
		] );

		$this->assertEquals(
			[
				'results' => [],
			],
			$result
		);
	}

	public function testListDepositsThrowsOnHttpError() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->willReturn( [
				'body' => '{"error":"bad request"}',
				'headers' => [],
				'status' => 400,
			] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Chariot request to https://api.givechariot.com/v1/deposits failed with HTTP 400' );

		$this->api->listDeposits();
	}

	public function testListDepositsThrowsOnInvalidJson() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->willReturn( [
				'body' => 'not-json',
				'headers' => [],
				'status' => 200,
			] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Chariot API returned invalid JSON' );

		$this->api->listDeposits();
	}

	public function testListDepositsThrowsOnApiErrorField() {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->willReturn( [
				'body' => json_encode( [
					'error' => 'something went wrong',
				] ),
				'headers' => [],
				'status' => 200,
			] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Chariot API error: something went wrong' );

		$this->api->listDeposits();
	}
}
