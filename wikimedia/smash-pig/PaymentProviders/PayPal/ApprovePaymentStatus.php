<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class ApprovePaymentStatus implements StatusNormalizer {

	/**
	 * @see https://developer.paypal.com/api/nvp-soap/do-express-checkout-payment-nvp/#link-doexpresscheckoutpaymentresponsemessage
	 *
	 * @param string $paypalStatus
	 * @return string
	 */
	public function normalizeStatus( string $paypalStatus ): string {
		switch ( $paypalStatus ) {
			case 'None':
				$status = FinalStatus::UNKNOWN;
				break;
			case 'Processed':
			case 'Completed':
			case 'Canceled-Reversal':
				$status = FinalStatus::COMPLETE;
				break;
			case 'Denied':
			case 'Failed':
			case 'Voided':
				$status = FinalStatus::FAILED;
				break;
			case 'Expired':
				$status = FinalStatus::TIMEOUT;
				break;
			case 'In-Progress':
				$status = FinalStatus::PENDING;
				break;
			case 'Partially-Refunded':
			case 'Refunded':
				$status = FinalStatus::REFUNDED;
				break;
			case 'Reversed':
				$status = FinalStatus::REVERSED;
				break;
			case 'Completed_Funds_Held':
				$status = FinalStatus::ON_HOLD;
				break;
			default:
				throw new UnexpectedValueException( "Unknown PayPal status $paypalStatus" );
		}

		return $status;
	}
}
