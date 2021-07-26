<?php
namespace PHPUnit\Framework;

class TestCase extends \PHPUnit\Framework\Assert {
	/**
	 * @param class-string<\Throwable> $exception
	 */
	public function expectException( string $exception ): void {
		$this->expectedException = $exception;
	}

}
