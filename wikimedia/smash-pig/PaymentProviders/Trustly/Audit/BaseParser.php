<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Audit;

class BaseParser {

	protected array $row;

	public function __construct( array $row ) {
		$this->row = $row;
	}

}
