<?php
/**
 * Formats strings, numbers and times, providing internationalisation support.
 * @author Bart King
 */

final class CDFFormat
{
	/*
	 * Numeric formatting
	 */

	/**
	 * Formats a floating point number to a string.
	 * @param float $number Floating point number to format
	 * @return string Formatted number
	 */
	public static function DoubleToString($number)
	{
		return number_format($number, 4);
	}

	/**
	 * Formats an integer to a string.
	 * @param $int int Number to format
	 * @return string Formatted number
	 */
	public static function IntegerToString($int)
	{
		return number_format($int);
	}

	/**
	 * Formats a 64-bit integer to a string.
	 * @param $int64 int Number to format
	 * @return string Formatted number
	 */
	public static function Integer64ToString($int64)
	{
		return number_format($int64);
	}

	/**
	 * Formats a money value to a string.
	 * @param int $number Amount to format (123.45)
	 * @param string $currency Currency symbol to use
	 * @return string Formatted number
	 */
	public static function CurrencyToString($number, $currency)
	{
		return sprintf('%s %s', $currency, number_format($number, 2));
	}

	/**
	 * Converts a string-representation of a price to a whole integer value ("19.99" => 1999).
	 * It seems PHP has a bug in its float->int conversion (it has implicit rounding, but gets it very wrong sometimes).
	 * See: http://www.php.net/manual/en/function.intval.php#101439
	 * @param string $price String to convert, such as "19.99".
	 * @return int
	 */
	public static function PriceToInteger($price)
	{
		/* Here's a comparison of some of the internal casting conversions (from string) that occur in PHP.
		The formula for this test was: (int)((float)"19.99" * 100.0f)
					string(5) "19.99"
		float(19.99)
		float(1999)
		int(1998)  <-- wrong!
		string(5) "49.99"
		float(49.99)
		float(4999)
		int(4999) <-- right?
		*/

		return intval(strval(CDFDataHelper::AsFloat(sprintf('%01.2f', $price)) * 100.0));
	}
}
