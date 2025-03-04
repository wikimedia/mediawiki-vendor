<?php

namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

/**
 * This message is sent when an authorized transaction is voided on Gravy.
 * We need to ignore it as no action is required.
 */
class TechnicalCancel extends AdyenMessage {

}
