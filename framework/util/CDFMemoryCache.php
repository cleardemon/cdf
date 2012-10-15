<?php

interface CDFMemoryCacheKind
{
	const None = 0;
	const AlternativePHPCache = 1; // apc extension
	const MemCache = 2; // MemCache extension
}

/**
 * Allows for the use of in-memory caching functions in PHP to store keys and values.
 * Safe to call if no functions are available or enabled - if this is the case, nothing is cached.
 */
final class CDFMemoryCache
{
	const DefaultTTL = 3600;

	private $_cacheMode = CDFMemoryCacheKind::None;
	private $_ttl;
	/** @var Memcache */
	private $_memCacheResource = null;

	public function __construct($mode = CDFMemoryCacheKind::None, $ttl = self::DefaultTTL)
	{
		$this->_cacheMode = $mode;
		$this->_ttl = $ttl;

		// verify that the requested cache mode is available in this PHP installation
		$valid = true;
		switch($mode)
		{
			case CDFMemoryCacheKind::None:
				break;
			case CDFMemoryCacheKind::AlternativePHPCache:
				$valid = extension_loaded('apc') && function_exists('apc_store');
				break;
			case CDFMemoryCacheKind::MemCache:
				$valid = extension_loaded('memcache') && class_exists('Memcache');
				break;
		}
		if(!$valid)
			// requested cache mode is not available
			$this->_cacheMode = CDFMemoryCacheKind::None;
	}

	/**
	 * Initialises a connection to a caching server, if applicable. Not all caching modes require this to be called.
	 * Best practice should make sure that it is called, if caching servers change at a later date.
	 * @param string $host Hostname to connect to.
	 * @param int $port Network port to connect to. If null, will use default for the kind of cache server.
	 * @return bool True on success, false on failure.
	 */
	public function connect($host, $port = null)
	{
		if($this->_memCacheResource !== null)
		{
			$this->_memCacheResource->close();
			$this->_memCacheResource = null;
		}

		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::None:
				return true;
			case CDFMemoryCacheKind::AlternativePHPCache:
				// APC is per-process (in context of Apache) so nothing to connect to
				return true;
			case CDFMemoryCacheKind::MemCache:
				$this->_memCacheResource = new Memcache();
				// suppress any connection errors
				if(@$this->_memCacheResource->pconnect($host, $port))
					return true;
				// connection failed if here
				$this->_memCacheResource = null;
				break;
		}

		return false;
	}

	/**
	 * Returns true if there is a connection to a caching server.
	 * @return bool
	 */
	private function hasConnection()
	{
		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::MemCache:
				return $this->_memCacheResource !== null;
		}

		return false;
	}

	/**
	 * Adds or stores an item to the cache.
	 * @param bool $store If true, stores. If false, adds.
	 * @param string $key
	 * @param mixed $value
	 * @param int|bool $ttl
	 * @return bool
	 * @throws CDFInvalidArgumentException
	 */
	private function writeItemToCache($store, $key, $value, $ttl = false)
	{
		if($this->_cacheMode == CDFMemoryCacheKind::None)
			return false; // return false for no cache as it will never be cached

		// require key to be a string
		if(!is_string($key))
			throw new CDFInvalidArgumentException('Key must be a string');

		// check to use currently set TTL
		if($ttl === false)
			$ttl = $this->_ttl;

		// add or store item in cache
		$added = false;
		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::AlternativePHPCache:
				if($store)
					$added = apc_store($key, $value, $ttl);
				else
					$added = apc_add($key, $value, $ttl);
				break;
			case CDFMemoryCacheKind::MemCache:
				// require connection
				if($this->hasConnection())
				{
					if($store)
						$added = $this->_memCacheResource->set($key, $value, 0, $ttl);
					else
						$added = $this->_memCacheResource->add($key, $value, 0, $ttl);
				}
				break;
		}

		return $added;

	}

	/**
	 * Adds a single item to the cache. If the item already exists, it will not be changed.
	 * @param string $key Key of item to cache. Must be a string.
	 * @param mixed $value Value to cache.
	 * @param int|bool $ttl TTL for the item, or false to use default.
	 * @throws CDFInvalidArgumentException
	 * @return bool
	 */
	public function addItem($key, $value, $ttl = false)
	{
		return $this->writeItemToCache(false, $key, $value, $ttl);
	}

	/**
	 * Stores a single item in the cache. If the item already exists, it will be overwritten.
	 * @param string $key Key of item to cache. Must be a string.
	 * @param mixed $value Value to cache.
	 * @param int|bool $ttl TTL for the item, or false to use default.
	 * @throws CDFInvalidArgumentException
	 * @return bool
	 */
	public function storeItem($key, $value, $ttl = false)
	{
		return $this->writeItemToCache(true, $key, $value, $ttl);
	}

	/**
	 * Removes a single item from the cache. Nothing happens if the item is not cached.
	 * @param string $key Key of item to remove. Must be a string.
	 * @throws CDFInvalidArgumentException
	 */
	public function removeItem($key)
	{
		if($this->_cacheMode == CDFMemoryCacheKind::None)
			return;

		// require key to be a string
		if(!is_string($key))
			throw new CDFInvalidArgumentException('Key must be a string');

		// remove from the cache
		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::AlternativePHPCache:
				apc_delete($key);
				break;
			case CDFMemoryCacheKind::MemCache:
				if($this->hasConnection())
					$this->_memCacheResource->delete($key);
				break;
		}
	}

	/**
	 * Retrieves a single item from the cache. Returns null if not found.
	 * @param string $key Key of item to find. Must be a string.
	 * @return mixed|null
	 * @throws CDFInvalidArgumentException
	 */
	public function getItem($key)
	{
		if($this->_cacheMode == CDFMemoryCacheKind::None)
			return null;

		// require key to be a string
		if(!is_string($key))
			throw new CDFInvalidArgumentException('Key must be a string');

		$result = null;
		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::AlternativePHPCache:
				$result = apc_fetch($key);
				if($result === false)
					$result = null;
				break;
			case CDFMemoryCacheKind::MemCache:
				if($this->hasConnection())
				{
					$result = $this->_memCacheResource->get($key);
					if($result === false)
						$result = null;
				}
				break;
		}

		return $result;
	}

	/**
	 * Removes all items from the cache.
	 */
	public function invalidate()
	{
		switch($this->_cacheMode)
		{
			case CDFMemoryCacheKind::MemCache:
				if($this->hasConnection())
					$this->_memCacheResource->flush();
				break;
			case CDFMemoryCacheKind::AlternativePHPCache:
				apc_clear_cache();
				break;
		}
	}
}
