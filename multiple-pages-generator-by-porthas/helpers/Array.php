<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LimitedArray
 *
 * A class that implements ArrayAccess and Countable interfaces to manage an array with a limited number of elements.
 */
final class MpgArray implements ArrayAccess, Countable {
	/**
	 * @var array $container The internal array to store elements.
	 */
	private $container = [];

	/**
	 * @var int $limit The maximum number of elements allowed in the array.
	 */
	private $limit = 51;

	/**
	 * Constructor to initialize the array with an optional initial array.
	 *
	 * @param array $initialArray An optional array to initialize the container with.
	 * @param int $default_limit The default limit of the array.
	 */
	public function __construct( $initialArray = [], $default_limit = 0 ) {
		if ( $default_limit > 0 ) {
			$this->limit = $default_limit;
		}
		$this->container = array_slice( $initialArray, 0, $this->limit );
	}


	/**
	 * Countable method to get the number of elements in the array.
	 *
	 * @return int The number of elements in the array.
	 */
	#[\ReturnTypeWillChange]
	public function count() {
		return count( $this->container );
	}


	/**
	 * ArrayAccess method to set a value at a specific offset.
	 *
	 * @param mixed $offset The offset to set the value at.
	 * @param mixed $value The value to set.
	 *
	 * @throws OverflowException If the array limit is reached.
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $offset, $value ) {
		if ( $this->count() >= $this->limit ) {
			throw new OverflowException( "Array limit of {$this->limit} reached." );
		}

		if ( is_null( $offset ) ) {
			$this->container[] = $value; // Append if no offset is given
		} else {
			$this->container[ $offset ] = $value; // Set at a specific offset
		}
	}

	/**
	 * ArrayAccess method to check if an offset exists.
	 *
	 * @param mixed $offset The offset to check.
	 *
	 * @return bool True if the offset exists, false otherwise.
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $offset ) {
		return isset( $this->container[ $offset ] );
	}

	/**
	 * ArrayAccess method to unset a value at a specific offset.
	 *
	 * @param mixed $offset The offset to unset the value at.
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $offset ) {
		unset( $this->container[ $offset ] );
	}

	/**
	 * ArrayAccess method to get a value at a specific offset.
	 *
	 * @param mixed $offset The offset to get the value from.
	 *
	 * @return mixed The value at the specified offset, or null if the offset does not exist.
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->container[ $offset ] ?? null;
	}

	/**
	 * Method to convert the array to a simple array.
	 *
	 * @return array The array of all elements.
	 */
	public function toArray() {
		return $this->container;
	}
}