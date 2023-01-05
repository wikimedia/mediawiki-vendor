<?php

namespace SmashPig\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\SequenceGenerators\SqlSequenceGenerator;

class SequenceGeneratorTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var SqlSequenceGenerator
	 */
	protected $generator;

	public function setUp() : void {
		parent::setUp();
		$ctx = Context::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$this->generator = $globalConfig->object( 'sequence-generator/contribution-tracking' );
	}

	public function testInitialize() {
		$this->generator->initializeSequence( 5 );
		$id = $this->generator->getNext();
		$this->assertEquals( 6, $id );
	}

	public function testGetNext() {
		$this->generator->initializeSequence( 0 );
		$id = $this->generator->getNext();
		$this->assertSame( 1, $id );
		$id = $this->generator->getNext();
		$this->assertEquals( 2, $id );
	}
}
