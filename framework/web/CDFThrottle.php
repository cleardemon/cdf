<?php

require_once dirname(__FILE__) . '/../util/CDFMemoryCache.php';
require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';

/**
 * Throttling functionality
 *
 * Web APIs may be subjected to either spamming or denial-of-service attacks at some stage. In order to help
 * mitigate against such behaviour, request throttling can be introduced into endpoints to deny access to the resource
 * on a case-by-case basis.
 *
 * This isn't a be-all-and-end-all solution, but one tool available. Remember that this operates at application level,
 * so solutions that can exist at web server level or before (firewall) can also be beneficial.
 *
 * NOTE: This requires APC to be enabled and available in your PHP installation. Throws an exception if this isn't
 * the case.
 */
final class CDFThrottle
{
	/** @var string|null */
	static $_logLocation = null;

	/**
	 * Sets a path to write denied requests due to throttling. If null (default), no logs are written.
	 * @param string $path Log file path.
	 */
	public static function setLogLocation($path)
	{
		self::$_logLocation = $path;
	}

	/**
	 * Tests to see if the request should be throttled.
	 * @param string $context A name that identifies the type of request being performed.
	 * @param int $timeout Number of seconds that must elapse before throttling expires for the requester.
	 * @param int $maximumRequests Numbers of maximum requests allowed within the specified timeout. If zero, only timeout applies.
	 * @param string $requestIP The IP address of the request. If null, will discover it.
	 * @throws CDFInvalidOperationException
	 * @throws CDFInvalidArgumentException
	 * @return bool True if request should be throttled, false if allowed.
	 *
	 * @remarks
	 * <p>
	 * You can use different values of context in the same API endpoint depending on the type of request. For example,
	 * if the API has two modes, one to authenticate a user and another to create a new user, you may want to distinguish
	 * between those two modes and have different throttling rates.
	 * </p>
	 * <p>
	 * The timeout is specified in seconds. If this were 1 and maximumRequests were zero, only one request would be
	 * allowed per second. If timeout were 1 and maximumRequests were 2, only two requests per second pass the test.
	 * </p>
	 */
	public static function isRequestThrottled($context, $timeout, $maximumRequests = 0, $requestIP = null)
	{
		if(CDFDataHelper::isEmpty($context))
			throw new CDFInvalidArgumentException('Throttle context must be set');
		if($timeout < 1)
			throw new CDFInvalidArgumentException('Throttle timeout must be greater than zero');
		if($maximumRequests < 0)
			throw new CDFInvalidArgumentException('Throttle maximum requests must be non-negative');

		// find the request IP
		if($requestIP == null)
		{
			/* commented for now as this might actually be a security risk
			if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
				$requestIP = CDFDataHelper::AsStringSafe($_SERVER['HTTP_X_FORWARDED_FOR']);
			else*/if(isset($_SERVER['REMOTE_ADDR']))
				$requestIP = $_SERVER['REMOTE_ADDR'];
			else
				throw new CDFInvalidArgumentException('Throttle cannot determine request IP');
		}

		// get a handle on the memory cache
		$cache = new CDFMemoryCache(CDFMemoryCacheKind::AlternativePHPCache);
		// create key for the request
		$cacheKey = 'CDFThrottle.' . $context . '.' . $requestIP;

		// test if in cache
		$requestCount = $cache->getItem($cacheKey);
		if($requestCount == null)
		{
			// not in cache, meaning, not seen this IP yet
			$requestCount = 1;
			if($cache->storeItem($cacheKey, $requestCount, $timeout) == false)
				throw new CDFInvalidOperationException('Throttling not available as memory cache not present');
		}
		elseif($maximumRequests > 0)
		{
			// in cache and not expired, check request count is under limit
			$requestCount++;
			// store updated number. this also resets the timeout for the IP.
			$cache->storeItem($cacheKey, $requestCount, $timeout); // no need to check if stored (done earlier)
			// test if permitted
			if($requestCount >= $maximumRequests)
			{
				if(self::$_logLocation != null)
					error_log(sprintf('Denied request #%d after %d maximum for \'%s\' from %s', $requestCount, $maximumRequests, $context, $requestIP), 3, self::$_logLocation);
				return true; // not permitted
			}
		}
		else
		{
			// no maximum requests defined, and item hasn't expired yet, meaning it is throttled
			if(self::$_logLocation != null)
				error_log(sprintf('Denied request for \'%s\' from %s', $context, $requestIP), 3, self::$_logLocation);
			return true;
		}

		// if here, allow request
		return false;
	}
}
