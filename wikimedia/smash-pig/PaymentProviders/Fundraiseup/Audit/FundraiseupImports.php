<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;

class FundraiseupImports {

	/**
	 * @throws \Exception
	 */
	public function parse( $path ) {
		$csv = new HeadedCsvReader( $path, ',', 4098, 0 );
		$fileData = [];

		while ( $csv->valid() ) {
			try {
				$row = $this->parseLine( $csv );
				if ( !empty( $row ) ) {
					$fileData[] = $row;
				}
				$csv->next();
			} catch ( \Exception $exception ) {
				Logger::error( 'DataFileException: ' . $exception->getMessage() );
				throw $exception;
			}
		}

		return $fileData;
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @throws \Exception
	 */
	protected function parseLine( HeadedCsvReader $csv ) {
		$msg = [];
		$msg['gateway'] = 'fundraiseup';
		foreach ( $this->importMap as $header => $mappedProperty ) {
			try {
				$msg[$mappedProperty] = $csv->currentCol( $header );
			} catch ( \Exception $exception ) {
				Logger::warning( $exception->getMessage() );
				throw $exception;
			}
		}
		if ( !empty( $msg['payment_method'] ) ) {
			$this->setPaymentMethod( $msg );
		}
		if ( !empty( $msg['payment_submethod'] ) ) {
			$this->setPaymentSubmethod( $msg );
		}

		if ( !empty( $msg['receipt_date'] ) ) {
			$msg['receipt_date'] = strtotime( $msg['receipt_date'] );
		}
		if ( !empty( $msg['date'] ) ) {
			$msg['date'] = strtotime( $msg['date'] );
		}
		if ( !empty( $msg['frequency_unit'] ) && strtolower( $msg['frequency_unit'] ) != 'one time' ) {
			$msg['frequency_unit'] = $this->transformRecurringFrequency( $msg['frequency_unit'] );
			$msg['frequency_interval'] = 1;
		}
		return $msg;
	}

	protected function setPaymentMethod( &$msg ): void {
		$paymentMethods = [
			'credit card' => 'cc',
			'google pay' => 'google',
			'apple pay' => 'apple',
			'paypal' => 'paypal',
			'ach' => 'bt',
			'direct debit' => 'dd',
			'venmo' => 'venmo'
		];

		$paymentMethod = $msg['payment_method'];
		$msg['payment_method'] = $paymentMethods[strtolower( $paymentMethod )] ?? $msg['payment_method'] ?? null;
		if ( $msg['payment_method'] === 'bt' ) {
			$msg['payment_submethod'] = $paymentMethod;
		}
	}

	protected function setPaymentSubmethod( &$msg ): void {
		// using values from stripes support: https://stripe.com/docs/card-brand-choice
		$paymentSubmethods = [
			'visa' => 'visa',
			'mastercard' => 'mc',
			'amex' => 'amex',
			'cartes_bancaires' => 'cb',
			'diners' => 'diners',
			'discover' => 'discover',
			'jcb' => 'jcb',
			'unionpay' => 'unionpay'
		];

		$paymentSubmethod = $msg['payment_submethod'];
		$msg['payment_submethod'] = $paymentSubmethods[strtolower( $paymentSubmethod )] ?? $paymentSubmethod ?? null;
	}

	/**
	 * @param string $msg
	 */
	protected function transformRecurringFrequency( $frequency ) {
		$mapper = [
			'Monthly' => 'month',
			'Daily' => 'day',
			'Weekly' => 'week',
			'Yearly' => 'year'
		];
		return $mapper[$frequency] ?? '';
	}

	/**
	 * Country fallback
	 *
	 * The audit file contains the URL that the donor used
	 * to make the donation, so we try to extract the country
	 * code from that URL here and use it for the country field.
	 *
	 * @param string $donationURL
	 *
	 * @return string
	 */
	protected function getCountryFromDonationURL( string $donationURL ): string {
		$countryCode = '';
		$parts = parse_url( $donationURL );
		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			// Validate if 'country' is present, not empty, and is a two-letter country code
			if ( isset( $query['country'] ) && preg_match( '/^[A-Za-z]{2}$/', $query['country'] ) ) {
				$countryCode = $query['country'];
			}
		}

		return $countryCode;
	}
}
