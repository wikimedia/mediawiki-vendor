<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Represents a newly-created payment. Used to differ from the PaymentDetailResponse
 * because it had the redirect URL and data properties, but we had to move those to
 * the parent class. Perhaps someday it'll get other unique properties.
 *
 * Class CreatePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentResponse extends PaymentDetailResponse {

}
