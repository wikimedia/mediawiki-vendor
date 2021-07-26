<?php
namespace PHPUnit\Framework;

abstract class Assert {

    /**
     * Asserts the number of elements of an array, Countable or Traversable.
     *
     * @param Countable|iterable $haystack
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     * @throws ExpectationFailedException
     */
    public static function assertCount(int $expectedCount, $haystack, string $message = ''): void
    {
    }

	/**
	 * Asserts that two variables are equal.
	 *
	 * @throws ExpectationFailedException
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 */
	public static function assertEquals( $expected, $actual, string $message = '', float $delta = 0.0, int $maxDepth = 10, bool $canonicalize = false, bool $ignoreCase = false ): void {
	}

    /**
     * Asserts that two variables are equal (ignoring case).
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws ExpectationFailedException
     */
    public static function assertEqualsIgnoringCase($expected, $actual, string $message = ''): void
    {
    }

	/**
	 * Asserts that a condition is true.
	 *
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 * @throws ExpectationFailedException
	 *
	 * @psalm-assert true $condition
	 */
	public static function assertTrue( $condition, string $message = '' ): void {
	}

    /**
     * Asserts that a variable is null.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws ExpectationFailedException
     *
     * @psalm-assert null $actual
     */
    public static function assertNull($actual, string $message = ''): void
    {
    }

	/**
	 * Asserts that a variable is not null.
	 *
	 * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
	 * @throws ExpectationFailedException
	 *
	 * @psalm-assert null $actual
	 */
	public static function assertNotNull($actual, string $message = ''): void
	{
	}

    /**
     * Asserts that two variables have the same type and value.
     * Used on objects, it asserts that two variables reference
     * the same object.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws ExpectationFailedException
     *
     * @psalm-template ExpectedType
     * @psalm-param ExpectedType $expected
     * @psalm-assert =ExpectedType $actual
     */
    public static function assertSame($expected, $actual, string $message = ''): void
    {
    }

    /**
     * Asserts that a variable is of a given type.
     *
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     * @throws ExpectationFailedException
     *
     * @psalm-template ExpectedType of object
     * @psalm-param class-string<ExpectedType> $expected
     * @psalm-assert =ExpectedType $actual
     */
    public static function assertInstanceOf(string $expected, $actual, string $message = ''): void
    {
    }

	/**
	 * Mark the test as skipped.
	 *
	 * @throws SkippedTestError
	 * @throws SyntheticSkippedError
	 *
	 * @psalm-return never-return
	 */
	public static function markTestSkipped( string $message = '' ): void {
 }
}
