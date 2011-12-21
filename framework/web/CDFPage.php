<?php
	/**
	 * Various utility for HTTP when working with browser-requested resources.
	 * This used to be a wrapper for Savant but template functionality is done better with other libraries.
	 */

	require_once dirname(__FILE__) . '/../core/CDFExceptions.php';
	require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';

	class CDFPage
	{
		/**
		 * Tests if the current request is over HTTPS.
		 * @return bool True if the remote request is via HTTPS
		 */
		public function isHttpSecure()
		{
			return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
		}

		/**
		 * Redirects the client via HTTP to a relative (or absolute) URL.
		 * Script execution will end if calling this.
		 * @param string $url Relative or absolute URL to redirect.
		 */
		public function redirect($url)
		{
			$url = CDFDataHelper::AsStringSafe($url);
			if(strlen($url) == 0)
				throw new CDFInvalidArgumentException('URI in redirect is not specified');
			if(headers_sent())
				throw new CDFInvalidOperationException('Cannot redirect at this time');

			if($url[0] == '/')
				// uri is relative to the site, make it absolute
				$url = sprintf('%s://%s%s', $this->isHttpSecure() ? 'https' : 'http', $_SERVER['HTTP_HOST'], $url);

			header('Location: ' . $url);
			exit; // don't do anything else!
		}

		/**
		 * Returns the current URI/URL for the request, optionally stripping the query string.
		 * @param bool $keepQuery Set to true to preserve the query string, false to remove it.
		 * @return string
		 */
		public static function getCurrentUri($keepQuery = true)
		{
			$url = $_SERVER['REQUEST_URI'];
			if($keepQuery == false)
			{
				$pos = strpos($url, '?');
				if($pos !== false)
				{
					if($pos > 0)
						$url = substr($url, 0, $pos);
					else
						$url = '/'; // if url is "?foo=bar" (with no actual path) then redirect to the root
				}
			}

			return $url;
		}

		/**
		 * Causes the client to redirect to the same page.
		 * Script execution will end if calling this.
		 * @param bool $keepQuery Set to true to preserve the query string, false to remove it.
		 */
		public function reload($keepQuery = true)
		{
			$this->redirect($this->getCurrentUri($keepQuery));
		}

		//
		// Post back
		//

		/**
		 * Tests if the current request has HTTP POST data, optionally originating from a named "submit" button.
		 * @param string|null $submitName Name of the <input type="submit"> form field or null if don't care.
		 * @return bool True if post back detected.
		 */
		public function isPostBack($submitName = null)
		{
			// if no submit button name passed in, just check if there was any post variables
			if($submitName == null)
				return count($_POST) > 0;

			// check if the specified form field is present in the postback
			return isset($_POST[$submitName]);
		}

		/**
		 * Gets the specified variable from the POST array.
		 * @param string $name Key for the POST field
		 * @param bool $unsafe If true, will not pass it to safe string parsing. Use with absolute caution.
		 * @return string|null Value of the field or null if not found
		 */
		public function getPostVariable($name, $unsafe = false)
		{
			if(isset($_POST[$name]))
				return $unsafe === true ? $_POST[$name] : CDFDataHelper::AsStringSafe($_POST[$name], true);

			return null;
		}

		/**
		 * Returns true if the specified variable is in the postback.
		 * @param string $name Key for the POST field.
		 * @return bool True if the variable was found.
		 */
		public function hasPostVariable($name)
		{
			return isset($_POST[$name]) ? true : false;
		}

	}
