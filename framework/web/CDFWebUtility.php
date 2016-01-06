<?php

require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';
require_once dirname(__FILE__) . '/../core/CDFExceptions.php';

/**
 * Utility functions for dealing with various HTTP-based scenarios.
 */
final class CDFWebUtility
{
	static $_headerCache = null;

	/**
	 * Gets all the headers sent in the original request by the client.
	 * Only caveat of this is that all keys in the array will be returned
	 * in camel case.
	 * Example:
	 *   AWESOME_KEY => Awesome-Key
	 *   Fantastic Header => Fantastic-Header
	 *
	 * Passing true to $force will make this skip using a static cache.
	 * @param bool $force
	 * @return array
	 */
	public static function getAllRequestHeaders($force = false)
	{
		if(self::$_headerCache != null && $force == false)
			return self::$_headerCache;

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

		self::$_headerCache = $heads;
		return $heads;
	}

	/**
	 * Modifies a query string in a URL.
	 * $modItems is a keyed array for the parts to modify. If a value is null, that key is removed from the query.
	 * Items not specified in the array are unmodified and retained in the URL.
	 * @static
	 * @param $url string URL to change.
	 * @param $modItems Array of keys/values to change the query string.
	 * @return string
	 * @throws CDFInvalidArgumentException
	 */
	public static function modifyQueryString($url, $modItems)
	{
		if(!is_array($modItems))
			throw new CDFInvalidArgumentException('modItems must be an array');
		// parse url
		$parsedUrl = parse_url(CDFDataHelper::AsString($url));
		// get current query string
		$items = isset($parsedUrl['query']) ? explode('&', $parsedUrl['query']) : array();
		// iterate modItems
		foreach($modItems as $modKey => $modValue)
		{
			// if key exists and value is null, delete item
			if($modValue == null)
			{
				if(isset($items[$modKey]))
					// delete item
					unset($items[$modKey]);
				// if not set, don't allow null in query string
				continue;
			}
			// add/change item
			$items[$modKey] = CDFDataHelper::AsString($modValue);
		}

		// format query string into parsed array
		if(count($items) > 0)
		{
			$qs = array();
			foreach($items as $key => $value)
				$qs[] = sprintf('%s=%s', $key, $value);
			$parsedUrl['query'] = implode('&', $qs);
		}
		else
			unset($parsedUrl['query']); // in case all items were deleted

		// rebuild url
		// note: username and password is skipped
		return sprintf('%s%s%s%s%s%s',
			isset($parsedUrl['scheme']) ? $parsedUrl['scheme'].'://' : '',
			isset($parsedUrl['host']) ? $parsedUrl['host'] : '',
			isset($parsedUrl['port']) ? $parsedUrl['port'] : '',
			isset($parsedUrl['path']) ? $parsedUrl['path'] : '',
			isset($parsedUrl['query']) ? '?'.$parsedUrl['query'] : '',
			isset($parsedUrl['fragment']) ? '#'.$parsedUrl['fragment'] : ''
		);
	}

	/**
	 * Returns items in a keyed array from the query string of the specified URL.
	 * @static
	 * @param $url string
	 * @return array
	 */
	public static function getQueryString($url)
	{
		$parsedUrl = parse_url(CDFDataHelper::AsString($url));
		return isset($parsedUrl['query']) ? explode('&', $parsedUrl['query']) : array();
	}

	/**
	 * Returns the domain name (example.co.uk) from a hostname (www.foo.example.co.uk).
	 * @static
	 * @param $host string Hostname to parse
	 * @return null|string
	 */
	public static function getDomainFromHost($host)
	{
		// break apart host string
		$bits = explode('/', $host);
		// if first contains a colon, url has been specified
		if($bits[0] == ':')
			$host = $bits[2]; // skip scheme and ://
		// break apart host name at dots
		$bits = explode('.', $host);
		$i = count($bits) - 3;
		if($i < 2)                    // TODO: Needs a bit of cleanup
			return null;
		if(strlen($bits[($i + 2)]) == 2)
			$url = $bits[$i] . '.' . $bits[($i + 1)] . '.' . $bits[($i + 2)];
		else if(strlen($bits[($i + 2)]) == 0)
			$url = $bits[($i)] . '.' . $bits[($i + 1)];
		else
			$url = $bits[($i + 1)] . '.' . $bits[($i + 2)];
		return $url;
	}

	/**
	 * Returns a URL that is absolute from the current executing domain, port and scheme.
	 * @static
	 * @param string $uri A relative URL located in the current site or null for the current request.
	 * @return string
	 */
	public static function getLocalAbsoluteURL($uri = null)
	{
		if($uri == null)
			$uri = $_SERVER['REQUEST_URI'];
		$scheme = $_SERVER['SERVER_PORT'] == 443 ? 'https' : 'http';
		return sprintf('%s://%s%s', $scheme, $_SERVER['HTTP_HOST'], $uri);
	}

	/**
	 * Extracts an associated array (key/value pairs) from the PHP magic variable $http_response_header.
	 * Typically used after a call to file_get_contents('http://...').
	 * @param $responseHeaders string The contents of $http_response_header
	 * @return array
	 * @throws CDFInvalidObjectException
	 * @link http://php.net/manual/en/reserved.variables.httpresponseheader.php
	 * The returned array contains a key 'StatusCode' for the HTTP status code (such as 200, 400, etc.).
	 */
	public static function getFormattedResponseHeaders($responseHeaders)
	{
		if($responseHeaders === null || !is_array($responseHeaders))
			return array();

		$headers = array();
		foreach($responseHeaders as $k => $v)
		{
			// parse each header.. it's either:
			//  - Key: Value
			//  - HTTP/1.1 Code Status
			$line = explode(':', $v, 2); // explode on colon
			if(isset($line[1]))
			{
				// key/value pair
				$headers[trim($line[0])] = trim($line[1]);
			}
			else
			{
				// status
				if(preg_match('HTTP/[0-9\.]+\s+([0-9]+)#', $v, $code))
					$headers['StatusCode'] = CDFDataHelper::AsInt($code);
			}
		}

		return $headers;
	}
}

