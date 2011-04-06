<?php
	/**
	 * Utility functions for dealing with various HTTP-based scenarios.
	 *
	 * @author demon
	 */

	class CDFWebUtility
	{
		/*
		 * Gets all the headers sent in the original request by the client.
		 * Only caveat of this is that all keys in the array will be returned
		 * in camel case.
		 * Example:
		 *   AWESOME_KEY => Awesome-Key
		 *   Fantastic Header => Fantastic-Header
		 *
		 * Passing true to $force will make this skip using a static cache.
		 */
		static $_headercache = null;
		public static function GetAllRequestHeaders($force = false)
		{
			if(self::$_headercache != null && $force == false)
				return self::$_headercache;

			$heads = array();
			foreach($_SERVER as $k => $v)
			{
				// all client-sent headers are prefixed with HTTP_
				if(substr($k, 0, 5) == "HTTP_")
				{
					$k = str_ireplace('_', ' ', substr($k, 5));
					$k = str_ireplace(' ', '-', ucwords(strtolower($k)));
					$heads[$k] = $v;
				}
			}

			self::$_headercache = $heads;
			return $heads;
		}
	 }
