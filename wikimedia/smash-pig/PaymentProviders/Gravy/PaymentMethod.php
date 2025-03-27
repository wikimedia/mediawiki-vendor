<?php

namespace SmashPig\PaymentProviders\Gravy;

enum PaymentMethod: string {
	case ACH = 'ach';
	case CASH_OXXO = 'cash_oxxo';
	case CASH_PAGO_EFECTIVO = 'cash_pago_efectivo';
	case CASH_ABITAB = 'cash_abitab';
	case CASH_RED_PAGOS = 'cash_red_pagos';
	case CASH_BOLETO = 'cash_boleto';
	case BOLETO = 'boleto';
	case NETBANKING = 'netbanking';
	case PAYPAL = 'paypal';
	case VENMO = 'venmo';
	case PIX = 'pix';
	case PSE = 'pse';
	case BCP = 'bcp';
	case WEBPAY = 'webpay';

	public function toGravyValue(): string {
		return match ( $this ) {
			self::ACH => 'trustly',
			self::CASH_OXXO => 'oxxo',
			self::CASH_PAGO_EFECTIVO => 'pagoefectivo',
			self::CASH_ABITAB => 'abitab',
			self::CASH_RED_PAGOS => 'redpagos',
			self::CASH_BOLETO => 'boleto',
			default => $this->value, // For every other case, just return the enum's string value
		};
	}
}
