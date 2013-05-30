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
	 * @throws CDFInvalidOperationException
	 * @throws CDFInvalidArgumentException
	 */
	public function redirect($url)
	{
		$url = CDFDataHelper::AsStringSafe($url);
		if(strlen($url) == 0)
			throw new CDFInvalidArgumentException('URI in redirect is not specified');
		if(headers_sent() || !isset($_SERVER['HTTP_HOST'])) // some bogus requests come without a host header (bots?)
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

	/**
	 * Returns true if all the specified variables are in the postback.
	 * @param array|mixed $list An array of items, or a vararg list of items.
	 * @return bool True if all the variables were found.
	 */
	public function hasPostVariables($list)
	{
		if(!is_array($list))
			$list = func_get_args();
		foreach($list as $name)
			if(!isset($_POST[$name]))
				return false;
		return true;
	}

	//
	// Session
	//

	/**
	 * Computes an MD5 hash of the specified IP address. Returns null if IP is invalid.
	 * @param int|string $ip Dotted (IPV4) or colonic (IPV6) string representation of the address to hash.
	 * @return null|string
	 */
	private function getIPAddressHash($ip)
	{
		$bitData = '';
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
		{
			// ip is v4
			$bitData = base_convert(ip2long($ip), 10, 2);
		}
		else if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false)
		{
			// ip is v6
			// convert to in_addr representation
			$inAddress = inet_pton($ip);
			if($inAddress !== false)
			{
				for($bits = 15; $bits >= 0; $bits--)
					$bitData .= sprintf('%08b', ord($inAddress[$bits]));
			}
		}

		return strlen($bitData) ? md5($bitData) : null;
	}

	/**
	 * Begins a session for the current request. Returns false if cannot be started (to which request should be aborted)
	 * @param string $sName Name of the session, used in the session cookie. (default is 'SESSION')
	 * @param bool $secure True allows for better protection against session hijack. (default is true)
	 * @param string $cookieDomain Specifies the domain to set for the session cookie (default null, current site)
	 * @return bool
	 */
	public function startSession($sName = 'SESSION', $secure = true, $cookieDomain = null)
	{
		static $kFixationRemoteAddress = 'FixationRemoteAddress';
		static $kFixationRemoteAgent = 'FixationRemoteAgent';

		if($secure)
		{
			// forcefully disable potentially insecure session options
			ini_set('session.use_only_cookies', 1);
			ini_set('session.use_trans_sid', 0);
		}
		// configure cookie
 		session_set_cookie_params(0, '/', $cookieDomain, $secure, $secure) ;

		// record some detail relating to the client request for fixation
		$ipHash = $this->getIPAddressHash($_SERVER['REMOTE_ADDR']);
		if(isset($_SERVER['HTTP_USER_AGENT']))
			$agentHash = md5($_SERVER['HTTP_USER_AGENT']);
		else
			return false;

		// set the session name
		session_name($sName);
		// start session
		if(!session_start())
			return false;

		// test against any previously recorded fixation detail
		$ok = true;
		if(isset($_SESSION[$kFixationRemoteAddress]))
			$ok = strcmp($_SESSION[$kFixationRemoteAddress], $ipHash) == 0;
		else
			$_SESSION[$kFixationRemoteAddress] = $ipHash;
		// user agent test
		if($ok && isset($_SESSION[$kFixationRemoteAgent])) // test ok to not clobber any previous fail
			$ok = strcmp($_SESSION[$kFixationRemoteAgent], $agentHash) == 0;
		else
			$_SESSION[$kFixationRemoteAgent] = $agentHash;

		return $ok;
	}

	/**
	 * Destroys all detail relating to the current session and expires any session cookie.
	 */
	public function destroySession()
	{
		// if no session, don't do anything
		$id = session_id();
		if(empty($id))
			return;

		@session_start();
		$name = session_name();
		session_regenerate_id(); // makes the previous session id entirely useless
		session_destroy();
		// expire named session cookie
		if(isset($_COOKIE[$name]))
			setcookie($name, null, time() - 3600, '/');
		// clear the global session array, but execution should really end by now
		$_SESSION = array();
	}
}
