<?php

namespace SmashPig\Tests\Http;

use SmashPig\Core\Context;
use SmashPig\Core\Http\CurlWrapper;
use SmashPig\Core\Http\HttpException;
use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;
use SmashPig\Core\Logging\LogStreams\ILogStream;
use SmashPig\Core\Logging\LogStreams\NullLogStream;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * Covers the curl/log-outbound and curl/redact-fields behaviour of CurlWrapper.
 *
 * execute() is run for real against a file:// URL with a single retry, so no
 * network is touched. The outbound data is logged before cURL runs, and log
 * events are captured through a log stream injected into the configuration.
 *
 * Assertions check that secrets are absent and surrounding data is present,
 * rather than pinning a particular mask format.
 *
 * @group Curl
 * @covers \SmashPig\Core\Http\CurlWrapper
 */
class CurlWrapperTest extends BaseSmashPigUnitTestCase {

	private const IBAN = 'DE89370400440532013000';
	private const CARD = 'test_4111111111111111';
	private const ORDER_ID = 'ORDER-12345';

	private const TEST_REDACT_FIELDS = [
		'sepa.ibanNumber',
		'encryptedCardNumber',
		'encryptedSecurityCode',
	];

	/**
	 * @var ILogStream Collects every event logged during a test
	 */
	private $logStream;

	public function setUp(): void {
		parent::setUp();
		$this->logStream = new class implements ILogStream {
			/** @var LogEvent[] */
			public array $events = [];

			public function processEvent( LogEvent $event ) {
				$this->events[] = $event;
			}

			public function registerContextHandler( LogContextHandler $ch ) {
			}

			public function enterContext( $contextNames ) {
			}

			public function renameContext( $contextNames, $oldTopName ) {
			}

			public function leaveContext( $contextNames ) {
			}

			public function shutdown() {
			}
		};
	}

	public function testTopLevelRedactedFieldIsMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::CARD, $line );
		$this->assertStringContainsString( 'encryptedCardNumber', $line );
		$this->assertStringContainsString( self::ORDER_ID, $line );
	}

	public function testNestedRedactedFieldIsMasked(): void {
		$this->configure();

		// Same shape as Adyen createSEPABankTransferPayment
		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'paymentMethod' => [
				'type' => 'sepadirectdebit',
				'sepa.ownerName' => 'Jane Donor',
				'sepa.ibanNumber' => self::IBAN,
			],
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::IBAN, $line );
		$this->assertStringContainsString( 'sepadirectdebit', $line );
		$this->assertStringContainsString( 'Jane Donor', $line );
	}

	public function testFieldsNotInListAreLoggedUnchanged(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'merchantAccount' => 'WikimediaTest',
			'amount' => [ 'value' => 1050, 'currency' => 'EUR' ],
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringContainsString( self::ORDER_ID, $line );
		$this->assertStringContainsString( 'WikimediaTest', $line );
		$this->assertStringContainsString( '1050', $line );
		$this->assertStringContainsString( 'EUR', $line );
	}

	public function testMultipleRedactedFieldsAtDifferentDepthsAreAllMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
			'paymentMethod' => [
				'encryptedSecurityCode' => 'test_737',
				'details' => [
					'sepa.ibanNumber' => self::IBAN,
				],
			],
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::CARD, $line );
		$this->assertStringNotContainsString( 'test_737', $line );
		$this->assertStringNotContainsString( self::IBAN, $line );
		$this->assertStringContainsString( self::ORDER_ID, $line );
	}

	/**
	 * Adyen (and anything else going through OutboundRequest) hands
	 * CurlWrapper a JSON string, not an array.
	 */
	public function testJsonStringBodyIsMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), json_encode( [
			'reference' => self::ORDER_ID,
			'paymentMethod' => [
				'type' => 'sepadirectdebit',
				'sepa.ibanNumber' => self::IBAN,
			],
		] ) );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::IBAN, $line );
		$this->assertStringContainsString( self::ORDER_ID, $line );
	}

	/**
	 * OutboundRequest::setBody() turns arrays into a query string before
	 * CurlWrapper sees them.
	 */
	public function testQueryStringBodyIsMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), http_build_query( [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
		] ) );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::CARD, $line );
		$this->assertStringContainsString( self::ORDER_ID, $line );
	}

	/**
	 * http_build_query() encodes nested keys as paymentMethod%5Bsepa.ibanNumber%5D
	 */
	public function testNestedQueryStringBodyIsMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), http_build_query( [
			'reference' => self::ORDER_ID,
			'paymentMethod' => [
				'type' => 'sepadirectdebit',
				'sepa.ibanNumber' => self::IBAN,
			],
		] ) );

		$line = $this->getSingleOutboundLine();
		$this->assertStringNotContainsString( self::IBAN, $line );
		$this->assertStringContainsString( self::ORDER_ID, $line );
		$this->assertStringContainsString( 'sepadirectdebit', $line );
	}

	public function testQueryStringWithoutRedactedFieldsIsLoggedUnchanged(): void {
		$this->configure();
		$body = http_build_query( [ 'reference' => self::ORDER_ID, 'note' => 'a&b=c' ] );

		$this->runExecute( new CurlWrapper(), $body );

		$this->assertSame( "Outbound data: '$body'", $this->getSingleOutboundLine() );
	}

	public function testUnstructuredStringBodyIsLoggedAsIs(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), 'plain text body' );

		$this->assertStringContainsString( 'plain text body', $this->getSingleOutboundLine() );
	}

	/**
	 * @dataProvider provideUnusualRedactedValues
	 */
	public function testRedactedFieldWithUnusualValueDoesNotCrash( $value ): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => $value,
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringContainsString( self::ORDER_ID, $line );
	}

	public static function provideUnusualRedactedValues(): array {
		return [
			'empty string' => [ '' ],
			'null' => [ null ],
			'integer' => [ 4111111111111111 ],
			'boolean' => [ false ],
		];
	}

	/**
	 * Documents the current matching rule: exact, case-sensitive key match.
	 * If the intended rule changes, update this test deliberately.
	 */
	public function testOnlyExactKeyMatchesAreMasked(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'ENCRYPTEDCARDNUMBER' => 'upper-case-key',
			'ibanNumber' => 'no-prefix-key',
		] );

		$line = $this->getSingleOutboundLine();
		$this->assertStringContainsString( 'upper-case-key', $line );
		$this->assertStringContainsString( 'no-prefix-key', $line );
	}

	public function testRequestSentToCurlKeepsRealValues(): void {
		$this->configure();
		$wrapper = $this->getRecordingWrapper();

		$this->runExecute( $wrapper, [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
			'paymentMethod' => [ 'sepa.ibanNumber' => self::IBAN ],
		] );

		$this->assertStringContainsString( self::CARD, $wrapper->sentData );
		$this->assertStringContainsString( self::IBAN, $wrapper->sentData );
	}

	public function testOutboundDataIsLoggedOnceAsReadableData(): void {
		$this->configure();

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
		] );

		$lines = $this->getOutboundLines();
		$this->assertCount( 1, $lines );
		$this->assertStringNotContainsString( "'Array'", $lines[0] );
		// The secret must not appear in any log line, not just the outbound one
		foreach ( $this->logStream->events as $event ) {
			$this->assertStringNotContainsString( self::CARD, (string)$event->message );
		}
	}

	public function testNothingLoggedWhenLogOutboundIsOff(): void {
		$this->configure( null, [ 'log-outbound' => false ] );

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
		] );

		$this->assertSame( [], $this->getOutboundLines() );
	}

	public function testProviderWithoutRedactFieldsGetsEmptyDefault(): void {
		$config = $this->configure( null, [], false );
		$this->assertSame( [], $config->val( 'curl/redact-fields' ) );

		$this->runExecute( new CurlWrapper(), 'reference=' . self::ORDER_ID );

		$this->assertStringContainsString( self::ORDER_ID, $this->getSingleOutboundLine() );
	}

	/**
	 * A provider that doesn't set curl/redact-fields must inherit the [] default
	 * from provider-defaults.yaml, including when it overrides other curl settings.
	 *
	 * @dataProvider provideProvidersWithoutRedactFields
	 */
	public function testProviderConfigWithoutRedactFieldsInheritsDefault( string $provider ): void {
		$config = $this->configure( $provider, [], false );
		$this->assertSame( [], $config->val( 'curl/redact-fields' ) );

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'encryptedCardNumber' => self::CARD,
		] );

		// Nothing is configured for redaction, so the body is logged as sent
		$line = $this->getSingleOutboundLine();
		$this->assertStringContainsString( self::ORDER_ID, $line );
		$this->assertStringContainsString( self::CARD, $line );
	}

	public static function provideProvidersWithoutRedactFields(): array {
		return [
			// Has its own curl block (validator) but no redact-fields
			'dlocal' => [ 'dlocal' ],
			// Has no curl block at all
			'gravy' => [ 'gravy' ],
		];
	}

	public function testAdyenOverrideKeepsDefaultCurlSettings(): void {
		$config = $this->configure( 'adyen', [], false );

		$this->assertSame(
			[
				'sepa.ibanNumber',
				'encryptedCardNumber',
				'encryptedExpiryMonth',
				'encryptedExpiryYear',
				'encryptedSecurityCode',
			],
			$config->val( 'curl/redact-fields' )
		);
		// Adyen only sets validator and redact-fields; the rest must still come from defaults
		$this->assertSame( 7, $config->val( 'curl/timeout' ) );
		$this->assertSame( 'SmashPig', $config->val( 'curl/user-agent' ) );
		$this->assertSame( CurlWrapper::class, $config->val( 'curl/wrapper/class' ) );
		$this->assertSame(
			'SmashPig\PaymentProviders\Adyen\RestResponseValidator',
			$config->val( 'curl/validator/class' )
		);
	}

	public function testAdyenConfigMasksSepaIban(): void {
		$this->configure( 'adyen', [], false );

		$this->runExecute( new CurlWrapper(), [
			'reference' => self::ORDER_ID,
			'paymentMethod' => [ 'sepa.ibanNumber' => self::IBAN ],
		] );

		$this->assertStringNotContainsString( self::IBAN, $this->getSingleOutboundLine() );
	}

	// === Helpers ===

	/**
	 * Builds a provider configuration with outbound logging captured by $this->logStream
	 * and makes it the current context's configuration.
	 *
	 * @param string|null $provider null for provider defaults only
	 * @param array $curlOverrides merged over curl/*
	 * @param bool $useTestRedactFields false to keep whatever the provider config sets
	 * @return ProviderConfiguration
	 */
	private function configure(
		?string $provider = null, array $curlOverrides = [], bool $useTestRedactFields = true
	): ProviderConfiguration {
		$globalConfig = Context::get()->getGlobalConfiguration();
		$config = $provider
			? TestingProviderConfiguration::createForProvider( $provider, $globalConfig )
			: TestingProviderConfiguration::createDefault( $globalConfig );

		$curl = [ 'retries' => 1, 'log-outbound' => true ];
		if ( $useTestRedactFields ) {
			$curl['redact-fields'] = self::TEST_REDACT_FIELDS;
		}
		$config->override( [
			'curl' => array_merge( $curl, $curlOverrides ),
			'logging' => [
				'log-level' => LOG_DEBUG,
				'enabled-log-streams' => [ 'capture' ],
				'log-streams' => [ 'capture' => [ 'class' => NullLogStream::class ] ],
			],
		] );
		$config->overrideObjectInstance( 'logging/log-streams/capture', $this->logStream );

		// Re-initialises the Logger against this configuration
		Context::get()->setProviderConfiguration( $config );
		return $config;
	}

	/**
	 * A CurlWrapper that records the data it hands to cURL
	 */
	private function getRecordingWrapper(): CurlWrapper {
		return new class extends CurlWrapper {
			public $sentData = null;

			protected function getCurlOptions( $url, $method, $headers, $data, $logStream, $certPath, $certPassword ) {
				$this->sentData = $data;
				return parent::getCurlOptions( $url, $method, $headers, $data, $logStream, $certPath, $certPassword );
			}
		};
	}

	/**
	 * Runs execute() against a local file URL. There is nothing to answer the
	 * request, so an HttpException after the outbound log line is expected.
	 */
	private function runExecute( CurlWrapper $wrapper, $data ): void {
		try {
			$wrapper->execute( 'file:///dev/null', 'POST', [], $data );
		} catch ( HttpException $e ) {
			// expected, see above
		}
	}

	/**
	 * @return string[]
	 */
	private function getOutboundLines(): array {
		$lines = [];
		foreach ( $this->logStream->events as $event ) {
			if ( str_starts_with( (string)$event->message, 'Outbound data:' ) ) {
				$lines[] = $event->message;
			}
		}
		return $lines;
	}

	private function getSingleOutboundLine(): string {
		$lines = $this->getOutboundLines();
		$this->assertCount( 1, $lines, 'Expected exactly one "Outbound data" log line' );
		return $lines[0];
	}
}
