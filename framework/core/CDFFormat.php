<?php
	/**
	 * Formats strings, numbers and times, providing internationalisation support.
	 * @author demon
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
		 * @param int $number Number to format
		 * @return string Formatted number
		 */
		public static function IntegerToString($int)
		{
			return number_format($int);
		}

		/**
		 * Formats a 64-bit integer to a string.
		 * @param int $number Number to format
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
	}
