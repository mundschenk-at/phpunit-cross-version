<?php
/**
 * This file is part of PHPunit Cross-Version Compatibility.
 *
 * Copyright 2017-2023 Peter Putzer.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  ***
 *
 * @package mundschenk-atphpunit-cross-version
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Mundschenk\PHPUnit_Cross_Version;

/**
 * Abstract base class for cross-version PHPUnit tests using Brain Monkey.
 *
 * The class has been generalized from seperate classes in various WordPress plugins
 * I maintain.
 */
abstract class TestCase extends \Yoast\WPTestUtils\BrainMonkey\TestCase {

	/**
	 * Return encoded HTML string (everything except <>"').
	 *
	 * @param string $html A HTML fragment.
	 */
	protected function clean_html( $html ) {
		// Convert everything except Latin and Cyrillic and Thai.
		static $convmap = [
			// Simple Latin characters.
			0x80,   0x03ff,   0, 0xffffff, // @codingStandardsIgnoreLine.
			// Cyrillic characters.
			0x0514, 0x0dff, 0, 0xffffff, // @codingStandardsIgnoreLine.
			// Thai characters.
			0x0e7f, 0x10ffff, 0, 0xffffff, // @codingStandardsIgnoreLine.
		];

		return \str_replace( [ '&lt;', '&gt;' ], [ '<', '>' ], \mb_encode_numericentity( \htmlentities( $html, \ENT_NOQUOTES, 'UTF-8', false ), $convmap, 'UTF-8' ) );
	}

	/**
	 * Call protected/private method of a class.
	 *
	 * @param object $object      Instantiated object that we will run method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters  Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 *
	 * @throws \RuntimeException    The method could not be found in the object.
	 */
	protected function invoke_method( $object, $method_name, array $parameters = [] ) {

		$reflection = new \ReflectionObject( $object );
		while ( ! empty( $reflection ) ) {
			try {
				$method = $reflection->getMethod( $method_name );
				$method->setAccessible( true );
				return $method->invokeArgs( $object, $parameters );
			} catch ( \ReflectionException $e ) {
				// Try again with superclass.
				$reflection = $reflection->getParentClass();
			}
		}

		throw new \RuntimeException( "Method $method_name not found in object." );
	}

	/**
	 * Call protected/private method of a class.
	 *
	 * @param string $classname   A class that we will run the method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters  Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	protected function invoke_static_method( $classname, $method_name, array $parameters = [] ) {
		$reflection = new \ReflectionClass( $classname );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( null, $parameters );
	}

	/**
	 * Sets the value of a private/protected property of a class.
	 *
	 * @param string     $classname     A class whose property we will access.
	 * @param string     $property_name Property to set.
	 * @param mixed|null $value         The new value.
	 */
	protected function set_static_value( $classname, $property_name, $value ) {
		$reflection = new \ReflectionClass( $classname );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/**
	 * Sets the value of a private/protected property of a class.
	 *
	 * @param object     $object        Instantiated object that we will run method on.
	 * @param string     $property_name Property to set.
	 * @param mixed|null $value         The new value.
	 *
	 * @throws \RuntimeException    The attribute could not be found in the object.
	 */
	protected function set_value( $object, $property_name, $value ) {

		$reflection = new \ReflectionObject( $object );
		while ( ! empty( $reflection ) ) {
			try {
				$property = $reflection->getProperty( $property_name );
				$property->setAccessible( true );
				$property->setValue( $object, $value );
				return;
			} catch ( \ReflectionException $e ) {
				// Try again with superclass.
				$reflection = $reflection->getParentClass();
			}
		}

		throw new \RuntimeException( "Attribute $property_name not found in object." );
	}

	/**
	 * Retrieves the value of a private/protected property of a class.
	 *
	 * @param string $classname     A class whose property we will access.
	 * @param string $property_name Property to set.
	 *
	 * @return mixed
	 */
	protected function get_static_value( $classname, $property_name ) {
		$reflection = new \ReflectionClass( $classname );
		$property   = $reflection->getProperty( $property_name );
		$property->setAccessible( true );

		return $property->getValue();
	}

	/**
	 * Retrieves the value of a private/protected property of a class.
	 *
	 * @param object $object        Instantiated object that we will run method on.
	 * @param string $property_name Property to set.
	 *
	 * @return mixed
	 *
	 * @throws \RuntimeException    The attribute could not be found in the object.
	 */
	protected function get_value( $object, $property_name ) {
		$value_set  = false;
		$reflection = new \ReflectionObject( $object );
		while ( ! empty( $reflection ) ) {
			try {
				$property = $reflection->getProperty( $property_name );
				$property->setAccessible( true );
				$value     = $property->getValue( $object );
				$value_set = true;
				break;
			} catch ( \ReflectionException $e ) {
				// Try again with superclass.
				$reflection = $reflection->getParentClass();
			}
		}

		// To allow for null properties, we cannot use isset().
		if ( $value_set ) {
			return $value;
		}

		throw new \RuntimeException( "Attribute $property_name not found in object." );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object is not the same as $value.
	 *
	 * @param mixed  $value     The comparison value.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_same( $value, $attribute, $object, $message = '' ) {
		return $this->assertSame( $value, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object does not contain $value.
	 *
	 * @param mixed  $value     The comparison value.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_contains( $value, $attribute, $object, $message = '' ) {
		return $this->assertContains( $value, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object contains $value.
	 *
	 * @param mixed  $value     The comparison value.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_not_contains( $value, $attribute, $object, $message = '' ) {
		return $this->assertNotContains( $value, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object contains anything other than $data_type.
	 *
	 * @param string $data_type      The name of the datatype.
	 * @param string $attribute      The attribute name.
	 * @param object $object         The object.
	 * @param bool   $is_native_type Optional. whether the datatype is a PHP native data type, d or not. Default null.
	 * @param string $message        Optional. Default ''.
	 */
	protected function assert_attribute_contains_only( $data_type, $attribute, $object, $is_native_type = null, $message = '' ) {
		return $this->assertContainsOnly( $data_type, $this->get_value( $object, $attribute ), $is_native_type, $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object contains only $data_type.
	 *
	 * @param string $data_type      The name of the datatype.
	 * @param string $attribute      The attribute name.
	 * @param object $object         The object.
	 * @param bool   $is_native_type Optional. whether the datatype is a PHP native data type, d or not. Default null.
	 * @param string $message        Optional. Default ''.
	 */
	protected function assert_attribute_contains_not_only( $data_type, $attribute, $object, $is_native_type = null, $message = '' ) {
		return $this->assertContainsNotOnly( $data_type, $this->get_value( $object, $attribute ), $is_native_type, $message );
	}

	/**
	 * Reports an error identified by $message if the number of elements in $attribute in $object is not $expected_count.
	 *
	 * @param int    $expected_count The expected number of elements.
	 * @param string $attribute      The attribute name.
	 * @param object $object         The object.
	 * @param string $message        Optional. Default ''.
	 */
	protected function assert_attribute_count( $expected_count, $attribute, $object, $message = '' ) {
		return $this->assertCount( $expected_count, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if the number of elements in $attribute in $object is $expected_count.
	 *
	 * @param int    $expected_count The expected number of elements.
	 * @param string $attribute      The attribute name.
	 * @param object $object         The object.
	 * @param string $message        Optional. Default ''.
	 */
	protected function assert_attribute_not_count( $expected_count, $attribute, $object, $message = '' ) {
		return $this->assertNotCount( $expected_count, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object is not empty.
	 *
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_empty( $attribute, $object, $message = '' ) {
		return $this->assertEmpty( $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object is empty.
	 *
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_not_empty( $attribute, $object, $message = '' ) {
		return $this->assertNotEmpty( $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object does not have the $key.
	 *
	 * @param string $key       The array key.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_array_has_key( $key, $attribute, $object, $message = '' ) {
		return $this->assertArrayHasKey( $key, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object does have the $key.
	 *
	 * @param string $key       The array key.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_array_not_has_key( $key, $attribute, $object, $message = '' ) {
		return $this->assertArrayNotHasKey( $key, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object is not an instance of $class.
	 *
	 * @param string $class     A class name.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_instance_of( $class, $attribute, $object, $message = '' ) {
		return $this->assertInstanceOf( $class, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $attribute in $object is an instance of $class.
	 *
	 * @param string $class     A class name.
	 * @param string $attribute The attribute name.
	 * @param object $object    The object.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_attribute_not_instance_of( $class, $attribute, $object, $message = '' ) {
		return $this->assertNotInstanceOf( $class, $this->get_value( $object, $attribute ), $message );
	}

	/**
	 * Reports an error identified by $message if $actual is not an array.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_array( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsArray' ) ) {
			return $this->assertIsArray( $actual, $message );
		} else {
			return $this->assertInternalType( 'array', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a boolean value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_bool( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsBool' ) ) {
			return $this->assertIsBool( $actual, $message );
		} else {
			return $this->assertInternalType( 'bool', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a float value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_float( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsFloat' ) ) {
			return $this->assertIsFloat( $actual, $message );
		} else {
			return $this->assertInternalType( 'float', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not an integer value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_int( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsInt' ) ) {
			return $this->assertIsInt( $actual, $message );
		} else {
			return $this->assertInternalType( 'int', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a numeric value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_numeric( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsNumeric' ) ) {
			return $this->assertIsNumeric( $actual, $message );
		} else {
			return $this->assertInternalType( 'numeric', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not an object.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_object( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsObject' ) ) {
			return $this->assertIsObject( $actual, $message );
		} else {
			return $this->assertInternalType( 'object', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a resource.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_resource( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsResource' ) ) {
			return $this->assertIsResource( $actual, $message );
		} else {
			return $this->assertInternalType( 'resource', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a string.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_string( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsString' ) ) {
			return $this->assertIsString( $actual, $message );
		} else {
			return $this->assertInternalType( 'string', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a scalar value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_scalar( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsScalar' ) ) {
			return $this->assertIsScalar( $actual, $message );
		} else {
			return $this->assertInternalType( 'scalar', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not a callable.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_callable( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsCallable' ) ) {
			return $this->assertIsCallable( $actual, $message );
		} else {
			return $this->assertInternalType( 'callable', $actual, $message );
		}
	}

	/**
	 * Reports an error identified by $message if $actual is not an iterable value.
	 *
	 * A custom method is used to future-proof the testcases as assertInternalType()
	 * has been deprecated in PHPUnit 8.
	 *
	 * @param  mixed  $actual  The value to test.
	 * @param  string $message Optional. Default ''.
	 */
	protected function assert_is_iterable( $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertIsIterable' ) ) {
			return $this->assertIsIterable( $actual, $message );
		} else {
			return $this->assertInternalType( 'iterable', $actual, $message );
		}
	}

	/**
	 * Expectes an exception to be thrown.
	 *
	 * @param  string $class The class name of the expected exception.
	 *
	 * @return void
	 */
	protected function expect_exception( $class ) {
		$this->expectException( $class );
	}

	/**
	 * Expectes an error to be thrown.
	 *
	 * @param  string $class The class name of the expected error.
	 *
	 * @return void
	 */
	protected function expect_error( $class ) {
		if ( \method_exists( $this, 'expectError' ) ) {
			$this->expectError( $class );
		} else {
			$this->expectException( $class );
		}
	}

	/**
	 * Expectes an warning to be thrown.
	 *
	 * @param  string $class The class name of the expected warning.
	 *
	 * @return void
	 */
	protected function expect_warning( $class ) {
		if ( \method_exists( $this, 'expectWarning' ) ) {
			$this->expectWarning( $class );
		} else {
			$this->expectException( $class );
		}
	}

	/**
	 * Expects a certain exception message.
	 *
	 * @param  string $regex A regular expression matching the exception message.
	 */
	protected function expect_exception_message_matches( $regex ) {
		if ( \method_exists( $this, 'expectExceptionMessageMatches' ) ) {
			$this->expectExceptionMessageMatches( $regex );
		} else {
			$this->expectExceptionMessageRegExp( $regex );
		}
	}

	/**
	 * Reports an error identified by $message if $haystack does not contain $needle.
	 *
	 * @param mixed  $needle    The needle.
	 * @param mixed  $haystack  The haystack.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_contains( $needle, $haystack, $message = '' ) {
		if ( \method_exists( $this, 'assertContainsEqual' ) ) {
			$this->assertContains( $needle, $haystack, $message );
		} else {
			$this->assertContains( $needle, $haystack, $message, false, true );
		}
	}

	/**
	 * Reports an error identified by $message if $haystack contains $needle.
	 *
	 * @param mixed  $needle    The needle.
	 * @param mixed  $haystack  The haystack.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_not_contains( $needle, $haystack, $message = '' ) {
		if ( \method_exists( $this, 'assertNotContainsEqual' ) ) {
			$this->assertNotContains( $needle, $haystack, $message );
		} else {
			$this->assertNotContains( $needle, $haystack, $message, false, true );
		}
	}

	/**
	 * Reports an error identified by $message if $haystack does not contain $needle using ==.
	 *
	 * @param mixed  $needle    The needle.
	 * @param mixed  $haystack  The haystack.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_contains_equals( $needle, $haystack, $message = '' ) {
		if ( \method_exists( $this, 'assertContainsEquals' ) ) {
			$this->assertContainsEquals( $needle, $haystack, $message );
		} else {
			$this->assertContains( $needle, $haystack, $message, false, false );
		}
	}

	/**
	 * Reports an error identified by $message if $haystack contains $needle using ==.
	 *
	 * @param mixed  $needle    The needle.
	 * @param mixed  $haystack  The haystack.
	 * @param string $message   Optional. Default ''.
	 */
	protected function assert_not_contains_equals( $needle, $haystack, $message = '' ) {
		if ( \method_exists( $this, 'asserNotContainsEquals' ) ) {
			$this->asserNotContainsEquals( $needle, $haystack, $message );
		} else {
			$this->assertNotContains( $needle, $haystack, $message, false, false );
		}
	}

	/**
	 * Asserts that the string matches the pattern. This ensures compatibility
	 * with PHPUnit 10 and higher.
	 *
	 * @param  string $pattern A regular expression.
	 * @param  string $string  The string.
	 * @param  string $message Optional. An error message. Default ''.
	 */
	protected function assert_matches_regular_expression( $pattern, $string, $message = '' ) {
		if ( \method_exists( $this, 'assertMatchesRegularExpression' ) ) {
			return $this->assertMatchesRegularExpression( $pattern, $string, $message );
		}

		return $this->assertRegExp( $pattern, $string, $message );
	}

	/**
	 * Asserts that the string does not match the pattern. This ensures compatibility
	 * with PHPUnit 10 and higher.
	 *
	 * @param  string $pattern A regular expression.
	 * @param  string $string  The string.
	 * @param  string $message Optional. An error message. Default ''.
	 */
	protected function assert_does_not_match_regular_expression( $pattern, $string, $message = '' ) {
		if ( \method_exists( $this, 'assertDoesNotMatchRegularExpression' ) ) {
			return $this->assertDoesNotMatchRegularExpression( $pattern, $string, $message );
		}

		return $this->assertNotRegExp( $pattern, $string, $message );
	}
}
