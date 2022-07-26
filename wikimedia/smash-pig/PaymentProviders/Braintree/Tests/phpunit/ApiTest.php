<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentProviders\Braintree\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Braintree
 */
class ApiTest extends BaseSmashPigUnitTestCase {

	/** @var Api */
	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfiguration = $this->setProviderConfiguration( 'braintree' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		$this->api = new Api( [
			'merchant-id' => 'mid1234',
			'public-key' => 'pk3333',
			'private-key' => 'privk33337777',
			'endpoint' => 'https://payments.braintree-api.com/graphql',
			'version' => '2022-03-07'
		] );
	}

	public function testCreateClientToken(): void {
		$contents = file_get_contents( __DIR__ . '/../Data/createClientToken.response' );
		$header_size = strpos( $contents, "\r\n\r\n" ) + 4;
		$parsed = CurlWrapper::parseResponse(
			$contents, [ 'http_code' => 200, 'header_size' => $header_size ]
		);
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				'https://payments.braintree-api.com/graphql',
				'POST',
				[
					'Authorization' => 'Basic cGszMzMzOnByaXZrMzMzMzc3Nzc=',
					'Braintree-Version' => '2022-03-07',
					'Content-Type' => 'application/json',
					'Content-Length' => '58'
				],
				'{"query":"mutation { createClientToken { clientToken } }"}'
			)
			->willReturn( $parsed );
		$response = $this->api->createClientToken();
		$this->assertEquals( [
			'data' => [ 'createClientToken' => [
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'clientToken' => 'eyJ2ZXJzaW9uIjoyLCJhdXRob3JpemF0aW9u555555VycHJpbnQiOiJleUowZVhBaU9pSktWMVFpTENKaGJHY2lPaUpGVXpJMU5pSXNJbXRwWkNJNklqSXdNVGd3TkRJMk1UWXRjMkZ1WkdKdmVDSXNJbWx6Y3lJNkltaDBkSEJ6T2k4dllYQnBMbk5oYm1SaWIzZ3VZbkpoYVc1MGNtVmxaMkYwWlhkaGVTNWpiMjBpZlEuZXlKbGVIQWlPakUyTlRNd01UQXhOelVzSW1wMGFTSTZJak5qTXpKaE5tVmtMV0k0TW1ZdE5ETXpOaTA1WlRObUxUQXlOV0l3WkRKaU9UWTNNeUlzSW5OMVlpSTZJbTF6T1daeGJYYzVaM2h5YlRKaWNETWlMQ0pwYzNNaU9pSm9kSFJ3Y3pvdkwyRndhUzV6WVc1a1ltOTRMbUp5WVdsdWRISmxaV2RoZEdWM1lYa3VZMjl0SWl3aWJXVnlZMmhoYm5RaU9uc2ljSFZpYkdsalgybGtJam9pYlhNNVpuRnRkemxuZUhKdE1tSndNeUlzSW5abGNtbG1lVjlqWVhKa1gySjVYMlJsWm1GMWJIUWlPbVpoYkhObGZTd2ljbWxuYUhSeklqcGJJbTFoYm1GblpWOTJZWFZzZENKZExDSnpZMjl3WlNJNld5SkNjbUZwYm5SeVpXVTZWbUYxYkhRaVhTd2liM0IwYVc5dWN5STZlMzE5LlVSUlRsc2lhdjMtTzZKaGJCREM0endrMTRCbG92c3JCa2FvdUNwclZwcWVoZzUxTjVHS1dGNWxDa3ZZWnFlZEFWSlhZR3QyTFB6TTlpX3dqWHJHaG9RIiwiY29uZmlnVXJsIjoiaHR0cHM6Ly9hcGkuc2FuZGJveC5icmFpbnRyZWVnYXRld2F5LmNvbTo0NDMvbWVyY2hhbnRzL21zOWZxbXc5Z3hybTJicDMvY2xpZW50X2FwaS92MS9jb25maWd1cmF0aW9uIiwiZ3JhcGhRTCI6eyJ1cmwiOiJodHRwczovL3BheW1lbnRzLnNhbmRib3guYnJhaW50cmVlLWFwaS5jb20vZ3JhcGhxbCIsImRhdGUiOiIyMDE4LTA1LTA4IiwiZmVhdHVyZXMiOlsidG9rZW5pemVfY3JlZGl0X2NhcmRzIl19LCJjbGllbnRBcGlVcmwiOiJodHRwczovL2FwaS5zYW5kYm94LmJyYWludHJlZWdhdGV3YXkuY29tOjQ0My9tZXJjaGFudHMvbXM5ZnFtdzlneHJtMmJwMy9jbGllbnRfYXBpIiwiZW52aXJvbm1lbnQiOiJzYW5kYm94IiwibWVyY2hhbnRJZCI6Im1zOWZxbXc5Z3hybTJicDMiLCJhc3NldHNVcmwiOiJodHRwczovL2Fzc2V0cy5icmFpbnRyZWVnYXRld2F5LmNvbSIsImF1dGhVcmwiOiJodHRwczovL2F1dGgudmVubW8uc2FuZGJveC5icmFpbnRyZWVnYXRld2F5LmNvbSIsInZlbm1vIjoib2ZmIiwiY2hhbGxlbmdlcyI6W10sInRocmVlRFNlY3VyZUVuYWJsZWQiOnRydWUsImFuYWx5dGljcyI6eyJ1cmwiOiJodHRwczovL29yaWdpbi1hbmFseXRpY3Mtc2FuZC5zYW5kYm94LmJyYWludHJlZS1hcGkuY29tL21zOWZxbXc5Z3hybTJicDMifSwicGF5cGFsRW5hYmxlZCI6dHJ1ZSwicGF5cGFsIjp7ImJpbGxpbmdBZ3JlZW1lbnRzRW5hYmxlZCI6dHJ1ZSwiZW52aXJvbm1lbnROb05ldHdvcmsiOnRydWUsInVudmV0dGVkTWVyY2hhbnQiOmZhbHNlLCJhbGxvd0h0dHAiOnRydWUsImRpc3BsYXlOYW1lIjoiV2lraW1lZGlhIEZvdW5kYXRpb24iLCJjbGllbnRJZCI6bnVsbCwicHJpdmFjeVVybCI6Imh0dHA6Ly9leGFtcGxlLmNvbS9wcCIsInVzZXJBZ3JlZW1lbnRVcmwiOiJodHRwOi8vZXhhbXBsZS5jb20vdG9zIiwiYmFzZVVybCI6Imh0dHBzOi8vYXNzZXRzLmJyYWludHJlZWdhdGV3YXkuY29tIiwiYXNzZXRzVXJsIjoiaHR0cHM6Ly9jaGVja291dC5wYXlwYWwuY29tIiwiZGlyZWN0QmFzZVVybCI6bnVsbCwiZW52aXJvbm1lbnQiOiJvZmZsaW5lIiwiYnJhaW50cmVlQ2xpZW50SWQiOiJtYXN0ZXJjbGllbnQzIiwibWVyY2hhbnRBY2NvdW50SWQiOiJ3aWtpbWVkaWFmb3VuZGF0aW9uIiwiY3VycmVuY3lJc29Db2RlIjoiVVNEIn19'
			] ],
			'extensions' => [
				'requestId' => '80f9dfbc-78fe-4d7e-89ad-03f46c9e50c8'
			]
		], $response );
	}
}
