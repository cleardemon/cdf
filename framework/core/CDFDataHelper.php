<?php

require_once 'CDFFormat.php';
require_once 'CDFExceptions.php';

/**
 *  Provides wrappers for guaranteeing a variable is the specified type.
 */
final class CDFDataHelper
{
	/**
	 * Formats any value passed in to be guaranteed as a string output.
	 * @param mixed $value
	 * @throws CDFInvalidArgumentException
	 * @return string
	 */
	public static function AsString($value)
	{
		if(is_null($value))
			return ''; // null returns an empty string
		if(is_string($value))
			return $value; // if already a string, don't do anything
		if(is_float($value))
			return CDFFormat::DoubleToString($value); // format it correctly
		if(is_int($value))
			return CDFFormat::IntegerToString($value);
		if(is_bool($value))
			return $value === true ? "True" : "False";
		if(is_object($value))
		{
			if(method_exists($value, '__toString'))
				$value = $value->__toString();
			else
				throw new CDFInvalidArgumentException('Cannot convert object to string');
		}

		// anything else, convert it to a string
		return trim((string)$value);
	}

	/**
	 * Returns the specified value as a string, but if it is already a string, applies 'safe' formatting to the string.
	 * @param mixed $value
	 * @param bool $stripHtml
	 * @return string
	 */
	public static function AsStringSafe($value, $stripHtml = true)
	{
		if(is_null($value))
			return '';
		if(!is_string($value))
			return self::AsString($value);

		// if stripping html, add anything inside a <> pair to remove from the string.
		return trim($stripHtml ? strip_tags($value) : $value);
	}

	/**
	 * Returns the specified value as a float.
	 * @param mixed $value
	 * @throws CDFInvalidArgumentException
	 * @return float
	 */
	public static function AsFloat($value)
	{
		if(is_float($value))
			return $value;
		if(is_null($value))
			return 0.0;
		if(is_string($value))
			return floatval(trim($value));
		if(is_object($value))
			throw new CDFInvalidArgumentException('Cannot convert object to float');

		// lazy conversion from anything else
		return 0.0 + $value;
	}

	/**
	 * Returns the specified value as a 32-bit integer.
	 * @param mixed $value
	 * @throws CDFInvalidObjectException
	 * @return int
	 */
	public static function AsInt($value)
	{
		if(is_int($value))
			return $value;
		if(is_null($value))
			return 0;
		if(is_string($value))
			return intval(trim($value)); // force conversion to integer
		if(is_object($value))
			throw new CDFInvalidObjectException('Cannot convert object to integer');

		return 0 + $value;
	}

	/**
	 * Returns the specified value as a boolean.
	 * @param mixed $value
	 * @return bool
	 */
	public static function AsBool($value)
	{
		if(is_bool($value))
			return $value;
		if(is_null($value))
			return false;
		if(is_string($value))
		{
			// special case to handle BIT fields in a database
			if(strlen($value) > 0 && $value[0] == chr(1))
				return true;

			$s = trim(strtolower($value));
			return ($s === '1' || $s === 'true' || $s === 'on' || $s === 'yes') ? true : false;
		}

		return $value ? true : false;
	}

	/**
	 * Returns the specified value as a DateTime object.  Will equal the Epoch if fails to parse.
	 * @param mixed $value
	 * @param string $timezone Defaults to GMT. Pass null to use system default
	 * @return DateTime
	 */
	public static function AsDateTime($value, $timezone = 'GMT')
	{
		if($value instanceof DateTime) // if already a DateTime object, just return it
		{
			// check its timezone first
			$tz = $timezone == null ? ini_get('date.timezone') : $timezone;
			/** @var $value DateTime */
			if($value->getTimezone()->getName() != $tz)
				// convert to GMT then to the timezone
			return CDFDataHelper::AsDateTime($value->getTimestamp(), $tz);
			// timezone is the same
			return $value;
		}
		if(is_null($value) || (is_string($value) && strlen($value) < 1))
			return new DateTime('@0');
		try
		{
			$tz = $timezone == null ? null : new DateTimeZone($timezone);
			if(is_numeric($value))
				return new DateTime(sprintf('@%d', $value), $tz);
			return new DateTime($value, $tz);
		}
		catch(Exception $ex)
		{
			return new DateTime('@0');
		}
	}

	/**
	 * Returns true if the specified valid is a valid DateTime object AND the time is not equal to the Epoch
	 * (i.e. it is actually a specified time).
	 * @param mixed $value
	 * @return bool
	 */
	public static function hasDateTime($value)
	{
		if($value instanceof DateTime)
		{
			/** @var $value DateTime */
			$ts = $value->getTimestamp(); // if negative, returns false
			return $ts !== false && $ts > 0;
		}
		return false;
	}

	/**
	 * Returns true if the specified array contains all of the defined keys.
	 * <code>
	 * echo CDFDataHelper::hasArrayKeys(array('foo'=>1,'bar'=>2), array('foo','bar'));
	 * // prints true
	 * echo CDFDataHelper::hasArrayKeys(array('foo'=>1,'bar'=>2), 'foo', 'bar');
	 * // also prints true
	 * echo CDFDataHelper::hasArrayKeys(array('foo'=>1,'bar'=>2), array('moo'));
	 * // prints false
	 * </code>
	 * @static
	 * @throws CDFInvalidArgumentException
	 * @param array $array The array to test against.
	 * @param array|mixed $keys Either an array of key names or a variable arg list of names.
	 * @return bool
	 */
	public static function hasArrayKeys($array, $keys)
	{
		// must be an array and must have at least one key to test
		if(!is_array($array) || func_num_args() < 2)
			throw new CDFInvalidArgumentException();

		if(!is_array($keys))
		{
			// argument is not an array, use variable args
			$keyNames = array();
			for($arg = 1; $arg < func_num_args(); $arg++)
				$keyNames[] = func_get_arg($arg);
		}
		else
			$keyNames = $keys;

		// test the array for the keys
		foreach($keyNames as $key)
		{
			if(!isset($array[$key]))
				return false;
		}

		return true;
	}

	/**
	 * Returns true if the specified value is 'empty', i.e. an empty string, zero, false or null.
	 * @remarks This wraps the PHP empty function where before PHP 5.5, empty() only accepts a variable and not an expression.
	 * @param mixed $value
	 * @return bool
	 */
	public static function isEmpty($value)
	{
		if($value instanceof DateTime)
			return self::hasDateTime($value);
		return empty($value);
	}
}
