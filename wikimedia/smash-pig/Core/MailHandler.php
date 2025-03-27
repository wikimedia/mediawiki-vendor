<?php namespace SmashPig\Core;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use SmashPig\Core\Logging\Logger;

/**
 * Abstraction on top of whatever email client we're actually using. For the moment that's
 * PHPMailer, pulled in via Composer.
 */
class MailHandler {

	/**
	 * Monolithic function to send an email!
	 *
	 * Several configuration nodes are required for this function:
	 * email/from-address      Default address for the From header
	 * email/bounce-address    Default address to use when VERPing the email.
	 *     IE: bounce+$1@contoso.com
	 * email/archive-addresses A list of addresses to always BCC when this function is used
	 *
	 * @param string $to Email address of recipient
	 * @param string $subject Subject line of email
	 * @param string $textBody Non HTML text of email (fallback text if $htmlBody is defined)
	 * @param null|string|array $from Email address of sender, if null is the value of the configuration
	 *                                     node 'email/from-address'. If passed as an array it is expected that
	 *                                     index 0 is the address and index 1 is the friendly name of the address.
	 * @param null|string $replyTo Address that recipient will reply to. If null will be set from the value
	 *                                     of $from.
	 * @param null|string $htmlBody HTML text of the email
	 * @param array $attach Paths to any attachments. These can have any legal PHP file descriptor.
	 * @param null|string|array $cc Carbon-Copy addresses.
	 * @param null|string|array $bcc Blind carbon-copy addresses. If specified these will always be in addition
	 *                                     to any archival addresses specified by the 'email/archive-addresses'
	 *                                     configuration node.
	 * @param bool|string $useVerp If true will set the MAIL FROM to the value specified under configuration
	 *                                     node 'email/bounce-address'. This can be overriden if a string is passed
	 *                                     instead of strict true. In either case, '$1' will be replaced by the
	 *                                     first $to address, RFC-3986 encoded.
	 *
	 * @return bool True if successfully sent. False if a PHPMailer exception occurred. Exceptions are logged at the
	 * warning level.
	 */
	public static function sendEmail( $to, $subject, $textBody, $from = null, $replyTo = null, $htmlBody = null,
		$attach = [], $cc = null, $bcc = null, $useVerp = true
	) {
		try {
			$config = Context::get()->getProviderConfiguration();
			$mailer = static::getMailer();

			$to = (array)$to;
			$cc = (array)$cc;
			$bcc = (array)$bcc;
			$archives = (array)$config->val( 'email/archive-addresses' );

			array_walk(
			$to,
			static function ( $value, $key ) use ( $mailer ) {
				$mailer->addAddress( $value );
			}
			);

			array_walk(
				$cc,
				static function ( $value, $key ) use ( $mailer ) {
					$mailer->addCC( $value );
				}
			);

			array_walk(
				$bcc,
				static function ( $value, $key ) use ( $mailer ) {
					$mailer->addBCC( $value );
				}
			);

			array_walk(
				$archives,
				static function ( $value, $key ) use ( $mailer ) {
					$mailer->addBCC( $value );
				}
			);

			array_walk(
				$attach,
				static function ( $value, $key ) use ( $mailer ) {
					$mailer->addAttachment( $value );
				}
			);

			// Set the from address
			if ( !$from ) {
				$from = $config->val( 'email/from-address' );
			}
			if ( is_array( $from ) ) {
				$mailer->setFrom( $from[ 0 ], $from[ 1 ] );
			} else {
				$mailer->setFrom( (string)$from );
			}

			// Only add reply to manually if requested, otherwise it's set when we call SetFrom
			if ( $replyTo ) {
				$mailer->addReplyTo( $replyTo );
			}

			// Set subject and body
			$mailer->Subject = $subject;
			if ( $htmlBody ) {
				$mailer->msgHTML( $htmlBody );
				$mailer->AltBody = $textBody;
			} else {
				$mailer->Body = $textBody;
			}

			// We replace $1 in email/bounce-address or useVerp if string to create the bounce addr
			if ( $useVerp ) {
				$sourceAddr = (array)$to;
				$sourceAddr = rawurlencode( $sourceAddr[ 0 ] );

				if ( is_string( $useVerp ) ) {
					$bounceAddr = $useVerp;
				} else {
					$bounceAddr = $config->val( 'email/bounce-address' );
				}

				$bounceAddr = str_replace( '$1', $sourceAddr, $bounceAddr );

				$mailer->Sender = $bounceAddr;
			}

			$mailer->send();

		} catch ( Exception $ex ) {
			$toStr = implode( ", ", $to );
			Logger::warning( "Could not send email to {$toStr}. PHP Mailer had exception.", null, $ex );
			return false;
		}

		return true;
	}

	/**
	 * Get instance of PHPMailer
	 *
	 * @return \PHPMailer\PHPMailer\PHPMailer
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	private static function getMailer(): PHPMailer {
		$config = Context::get()->getGlobalConfiguration();
		$mailer = new PHPMailer( true );
		if ( $config->val( 'mail-backend/smtp' ) === true ) {
			$mailer->isSMTP();
			$mailer->Host = $config->val( 'mail-backend/smtp-host' );
			$mailer->Port = $config->val( 'mail-backend/smtp-port' );
		} else {
			$mailer->isSendmail();
		}

		return $mailer;
	}

}
