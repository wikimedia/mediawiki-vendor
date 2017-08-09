<?php

namespace Tests\DataValues\Deserializers;

use DataValues\BooleanValue;
use DataValues\DataValue;
use DataValues\Deserializers\DataValueDeserializer;
use DataValues\NumberValue;
use DataValues\StringValue;
use Deserializers\Exceptions\DeserializationException;
use Deserializers\Exceptions\MissingAttributeException;
use Deserializers\Exceptions\MissingTypeException;
use Deserializers\Exceptions\UnsupportedTypeException;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;

/**
 * @covers DataValues\Deserializers\DataValueDeserializer
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DataValueDeserializerTest extends PHPUnit_Framework_TestCase {

	public function testGivenEmptyArray_isDeserializerForReturnsFalse() {
		$deserializer = $this->newDeserializer();
		$this->assertFalse( $deserializer->isDeserializerFor( array() ) );
	}

	private function newDeserializer() {
		return new DataValueDeserializer( array(
			'boolean' => function( $bool ) {
				return new BooleanValue( $bool );
			},
			'number' => NumberValue::class,
			'string' => StringValue::class,
		) );
	}

	/**
	 * @dataProvider notAnArrayProvider
	 */
	public function testGivenNonArray_isDeserializerForReturnsFalse( $notAnArray ) {
		$deserializer = $this->newDeserializer();
		$this->assertFalse( $deserializer->isDeserializerFor( $notAnArray ) );
	}

	public function notAnArrayProvider() {
		return array(
			array( null ),
			array( 0 ),
			array( true ),
			array( (object)array() ),
			array( 'foo' ),
		);
	}

	/**
	 * @dataProvider notADataValuesListProvider
	 */
	public function testGivenNonDataValues_constructorThrowsException( array $invalidDVList ) {
		$this->setExpectedException( InvalidArgumentException::class );

		new DataValueDeserializer( $invalidDVList );
	}

	public function notADataValuesListProvider() {
		return array(
			array(
				array(
					'foo',
					null,
					array(),
					true,
					42,
				)
			),
			array(
				array(
					'string' => 'foo',
				)
			),
			array(
				array(
					'string' => StringValue::class,
					'number' => 42,
				)
			),
			array(
				array(
					'string' => StringValue::class,
					'object' => 'stdClass',
				)
			)
		);
	}

	public function testGivenSerializationNoType_deserializeThrowsException() {
		$deserializer = $this->newDeserializer();

		$this->setExpectedException( MissingTypeException::class );
		$deserializer->deserialize( array() );
	}

	public function testGivenSerializationWithUnknownType_deserializeThrowsException() {
		$deserializer = $this->newDeserializer();

		$this->setExpectedException( UnsupportedTypeException::class );
		$deserializer->deserialize(
			array(
				'type' => 'ohi'
			)
		);
	}

	public function testGivenSerializationWithNoValue_deserializeThrowsException() {
		$deserializer = $this->newDeserializer();

		$this->setExpectedException( MissingAttributeException::class );
		$deserializer->deserialize(
			array(
				'type' => 'number'
			)
		);
	}

	/**
	 * @dataProvider invalidDataValueSerializationProvider
	 */
	public function testGivenInvalidDataValue_deserializeThrowsException( $invalidSerialization ) {
		$deserializer = $this->newDeserializer();

		$this->setExpectedException( DeserializationException::class );
		$deserializer->deserialize( $invalidSerialization );
	}

	public function invalidDataValueSerializationProvider() {
		return [
			[ 'foo' ],
			[ null ],
			[ [] ],
			[ [ 'hax' ] ],
			[ [ 'type' => 'hax' ] ],
			[ [ 'type' => 'number', 'value' => [] ] ],
			[ [ 'type' => 'boolean', 'value' => 'not a boolean' ] ],
		];
	}

	public function testInvalidValueSerialization_throwsDeserializationException() {
		$serialization = array(
			'value' => array( 0, 0 ),
			'type' => 'string',
			'error' => 'omg an error!'
		);

		$deserializer = $this->newDeserializer();
		$this->setExpectedException( DeserializationException::class );
		$deserializer->deserialize( $serialization );
	}

	/**
	 * @dataProvider dataValueSerializationProvider
	 */
	public function testGivenDataValueSerialization_isDeserializerForReturnsTrue( $dvSerialization ) {
		$deserializer = $this->newDeserializer();
		$this->assertTrue( $deserializer->isDeserializerFor( $dvSerialization ) );
	}

	public function dataValueSerializationProvider() {
		$boolean = new BooleanValue( false );
		$string = new StringValue( 'foo bar baz' );
		$number = new NumberValue( 42 );

		return array(
			array( $boolean->toArray(), 'boolean' ),
			array( $string->toArray(), 'string' ),
			array( $number->toArray(), 'number' ),
		);
	}

	/**
	 * @dataProvider dataValueSerializationProvider
	 */
	public function testGivenDataValueSerialization_deserializeReturnsDataValue( $dvSerialization, $expectedType ) {
		$deserializer = $this->newDeserializer();

		$dataValue = $deserializer->deserialize( $dvSerialization );

		$this->assertInstanceOf( DataValue::class, $dataValue );
		$this->assertEquals( $expectedType, $dataValue->getType() );
	}

}
