<?php
	require_once 'core/CDFFormat.php';
	require_once 'core/CDFExceptions.php';

	/**
	 *  Provides wrappers for guaranteeing a variable is the specified type.
	 */
	final class CDFDataHelper
	{
		/**
		 * Formats any value passed in to be guaranteed as a string output.
		 * @param mixed $value
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
			return trim((string) $value);
		}

		/**
		 * Returns the specified value as a string, but if it is already a string, applies 'safe' formatting to the string.
		 * @param mixed $value
		 * @param bool $striphtml
		 * @return string
		 */
		public static function AsStringSafe($value, $striphtml = true)
		{
			if(is_null($value))
				return '';
			if(!is_string($value))
				return self::AsString($value);

			// if stripping html, add anything inside a <> pair to remove from the string.
			return trim($striphtml ? strip_tags($value) : $value);
		}

		/**
		 * Returns the specified value as a float.
		 * @param mixed $value
		 * @return float
		 */
		public static function AsFloat($value)
		{
			if(is_float($value))
				return $value;
			if(is_null($value))
				return 0.0;
			if(is_string($value))
				return 0.0 + trim($value);
			if(is_object($value))
				throw new CDFInvalidArgumentException('Cannot convert object to float');

			// lazy conversion from anything else
			return 0.0 + $value;
		}

		/**
		 * Returns the specified value as a 32-bit integer.
		 * @param mixed $value
		 * @return int
		 */
		public static function AsInt($value)
		{
			if(is_int($value))
				return $value;
			if(is_null($value))
				return 0;
			if(is_string($value))
				return (int) (0 + trim($value)); // force conversion to integer
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
				$s = trim(strtolower($value));
				return ($s === '1' || $s === 'true' || $s === 'on' || $s === 'yes') ? true : false;
			}

			return $value ? true : false;
		}

		/**
		 * Returns the specified value as a DateTime object.  Will equal the Epoch if fails to parse.
		 * @param mixed $value
		 * @return DateTime
		 */
		public static function AsDateTime($value)
		{
			if($value instanceof DateTime) // if already a DateTime object, just return it
				return $value;
			if(is_null($value) || (is_string($value) && strlen($value) < 1))
				return new DateTime('@0');
			try
			{
				if(is_numeric($value))
					return new DateTime(sprintf('@%d', $value));
				return new DateTime($value);
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
				return $value->getTimestamp() == 0 ? false : true;
			return false;
		}
	}
