<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\PaymentProviders\Adyen\NotificationSignatureValidator;

/**
 * Keys, notifications and signatures marked as Adyen's below are copied from
 * tests/Unit/Util/HmacSignatureTest.php in Adyen's PHP library, so these tests
 * fail if we sign notifications differently from Adyen.
 * https://github.com/Adyen/adyen-php-api-library/tree/main
 *
 * @group Adyen
 */
class NotificationSignatureValidatorTest extends TestCase {

	protected string $test_hmac_key;
	protected string $test_invalid_hmac_key;
	protected array $test_notification;

	protected function setUp(): void {
		parent::setUp();
		// Adyen's key and notification
		$this->test_hmac_key = 'DFB1EB5485895CFA84146406857104ABB4CBCABDC8AAF103A624C8F6A3EAAB00';
		$this->test_notification = [
			'additionalData' => [
				'hmacSignature' => 'Ny9gS2veKo5E4w8/OL6xz1/wvT/hYkAXy1xNc/QvO4I=',
			],
			'pspReference' => '7914073381342284',
			'merchantAccountCode' => 'TestMerchant',
			'merchantReference' => 'TestPayment-1407325143704',
			'amount' => [
				'value' => 1130,
				'currency' => 'EUR',
			],
			'eventCode' => 'AUTHORISATION',
			'success' => 'true',
		];
		// Not configured for any account
		$this->test_invalid_hmac_key = 'A4D7082C892EB4297FCDFBE70863F037C3A993BC847DC329568D63A71A8EDBD2';
	}

	public function testCalculateSignatureMatchesAdyen(): void {
		// Adyen's signature for $this->test_notification. It has no
		// originalReference, which is signed as an empty string, so the
		// signed string is:
		// 7914073381342284::TestMerchant:TestPayment-1407325143704:1130:EUR:AUTHORISATION:true
		$this->assertSame(
			'Ny9gS2veKo5E4w8/OL6xz1/wvT/hYkAXy1xNc/QvO4I=',
			NotificationSignatureValidator::calculateSignature( $this->test_notification, $this->test_hmac_key )
		);
	}

	public function testCalculateSignatureWithZeroAmountMatchesAdyen(): void {
		// Adyen's zero amount test uses its own key
		$zeroAmountHmacKey = '44782DEF547AAA06C910C43932B1EB0C71FC68D9D0C057550C48EC2ACF6BA056';
		$notification = $this->test_notification;
		$notification['amount']['value'] = 0;
		$notification['eventCode'] = 'REFUND';

		$this->assertSame(
			'J7HhsgZo5KwqdB7LFZJV6rfQgp+RqC2kuYyw/3x3w+8=',
			NotificationSignatureValidator::calculateSignature( $notification, $zeroAmountHmacKey )
		);
	}

	/**
	 * The signed fields are joined with ':' and nothing is escaped, so a
	 * colon inside a value just becomes part of the signed string. This
	 * matters for gr4vy-orchestrated payments, where Adyen sends a
	 * merchantReference made of two gr4vy ids joined by a colon, e.g.
	 * '61SCO9jPrV41AIP8Wl5xkm:4Fn2bJ6cThbEiEdI1zGkYi' (see the gravy tests
	 * in NotificationRequestTest).
	 */
	public function testCalculateSignatureWithColonInMerchantReferenceMatchesAdyen(): void {
		// Adyen's test for a colon in merchantReference uses its own key and
		// notification, including its own merchant account. The signed string is:
		// TEST_CUSTOM_7914073381342284::PHPApiLibrary:auth:123:113000:EUR:AUTHORISATION:true
		$colonHmacKey = 'C56F00E99723D90F65254B00746844BED11BCDD0DD42B26EC980DC1301C6CD20';
		$notification = [
			'pspReference' => 'TEST_CUSTOM_7914073381342284',
			'merchantAccountCode' => 'PHPApiLibrary',
			'merchantReference' => 'auth:123',
			'amount' => [
				'value' => 113000,
				'currency' => 'EUR',
			],
			'eventCode' => 'AUTHORISATION',
			'success' => 'true',
		];

		$this->assertSame(
			'ovT21mqdbQToGbWssIhnBXAlnkhgKuehtGwvYFf5h2Q=',
			NotificationSignatureValidator::calculateSignature( $notification, $colonHmacKey )
		);
	}

	public function testAuthenticateValidSignature(): void {
		// passes as long as validateSignature() doesn't throw
		$this->expectNotToPerformAssertions();
		$this->getTestSignatureValidator()->validateSignature( $this->test_notification );
	}

	public function testMissingSignatureThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification has no hmacSignature' );

		$notification = $this->test_notification;
		unset( $notification['additionalData']['hmacSignature'] );
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testNonStringSignatureThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification has no hmacSignature' );

		// the valid signature, but inside an array
		$notification = $this->test_notification;
		$notification['additionalData']['hmacSignature'] = [ $notification['additionalData']['hmacSignature'] ];
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testInvalidCalculatedSignatureThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification hmacSignature does not match' );

		$notification = $this->test_notification;
		$notification['additionalData']['hmacSignature'] =
			NotificationSignatureValidator::calculateSignature( $notification, $this->test_invalid_hmac_key );
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testChangedAmountThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification hmacSignature does not match' );

		$notification = $this->test_notification;
		$notification['amount']['value'] = 1131;
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testChangedMerchantReferenceThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification hmacSignature does not match' );

		$notification = $this->test_notification;
		$notification['merchantReference'] = 'TestPayment-1407325143705';
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testNonStringSignedFieldThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'Notification has a signed field with a type Adyen never sends' );

		$notification = $this->test_notification;
		$notification['pspReference'] = [ $notification['pspReference'] ];
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testUnknownMerchantAccountThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'No valid webhook-hmac-key configured for merchant account' );

		$notification = $this->test_notification;
		$notification['merchantAccountCode'] = 'MerchantAccountNotInConfig';
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testMissingHmacKeyConfigThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'No valid webhook-hmac-key configured for merchant account' );

		$notification = $this->test_notification;
		$notification['merchantAccountCode'] = 'MerchantAccountWithoutHmacKey';
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	public function testEmptyHmacKeyConfigThrowsException(): void {
		$this->expectException( ListenerSecurityException::class );
		$this->expectExceptionMessage( 'No valid webhook-hmac-key configured for merchant account' );

		// signed with the empty key, so only the key check can reject it
		$notification = $this->test_notification;
		$notification['merchantAccountCode'] = 'MerchantAccountWithEmptyHmacKey';
		$notification['additionalData']['hmacSignature'] =
			NotificationSignatureValidator::calculateSignature( $notification, '' );
		$this->getTestSignatureValidator()->validateSignature( $notification );
	}

	private function getTestSignatureValidator(): NotificationSignatureValidator {
		$accounts = [
			// merchantAccountCode of Adyen's notification, part of the signed data
			'TestMerchant' => [ 'webhook-hmac-key' => $this->test_hmac_key ],
			'MerchantAccountWithoutHmacKey' => [ 'ws-api-key' => 'test-api-key' ],
			'MerchantAccountWithEmptyHmacKey' => [ 'webhook-hmac-key' => '' ],
		];

		return new NotificationSignatureValidator( $accounts );
	}
}
