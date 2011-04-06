<?php
	/**
	 * Allows the serialisation of variables to be stored inside a disk-based cache, preserved between request.
	 *
	 * Requires environment variable DISKCACHE_LOCATION to be set.
	 *
	 * @author demon
	 */

	class CDFDiskCache
	{
		/**
		 * @var string
		 */
		private $_location;

		const SUFFIX_METADATA = '.met';
		const SUFFIX_LOCK = '.lck';
		const SUFFIX_DATA = '.dat';

		const METADATA_EXPIRY = 'expiry';

		/**
		 * Creates instance of the cache.
		 * @param bool $cleancache Processes expired items in the cache, false to skip this.
		 */
		public function  __construct($cleancache = true)
		{
			$this->_location = $_ENV['DISKCACHE_LOCATION'];
			// check location exists
			if(!isset($this->_location) || !file_exists($this->_location))
				throw new Exception('DiskCache location not defined in environment.');

			// clean cache
			if($cleancache == true)
			{
				$now = time();
				$dir = opendir($this->_location);
				if($dir !== false)
				{
					$expires = array();
					while(($file = readdir($dir)) !== false)
					{
						if($file == '.' || $file == '..')
							continue;

						$pi = pathinfo($file);
						$key = $pi['filename'];
						if($pi['extension'] == self::SUFFIX_DATA)
						{
							// find metadata for this item
							$md = $this->getMetaData($key);
							if(array_key_exists(self::METADATA_EXPIRY, $md))
							{
								$exptime = CDFDataHelper::AsInt($md[self::METADATA_EXPIRY]);
								if($now >= $exptime)
								{
									// expire
									$expires[] = $key;
								}
							}
						}
					}

					// anything to clear?
					foreach($expires as $key)
						$this->remove($key);

					closedir($dir);
				}
			}
		}

		//
		// Main methods
		//

		/**
		 * Gets a value from the cache.
		 * Note: will block if the value is being written to by a different process.
		 *
		 * @param string $key Key to look up in the cache
		 * @return mixed|null The fetched data from the cache or NULL if not found.
		 */
		public function get($key)
		{
			$data = file_get_contents($this->getKeyFilename($key));
			if($data === false)
				return null;
			return unserialize($data);
		}

		/**
		 * Sets a value into the cache.
		 * Note: will block until it can write the value.
		 *
		 * @param string $key Key to use in the cache.
		 * @param mixed $value Value to set in the cache. May not be null.
		 * @param int $expiremins Defines the number of minutes (from time of set) that the item should expire from the cache.
		 * @return bool True if successful, false otherwise.
		 */
		public function set($key, $value, $expiremins = 120)
		{
			// attempt to lock value
			if(!$this->obtainLock($key))
				return false;

			// write the value
			if(file_put_contents($this->getKeyFilename($key), serialize($value)) === false)
			{
				$this->releaseLock($key);
				return false;
			}
			// write expiry time as metadata
			if($expiremins > 0)
				$this->setMetaData($key, self::METADATA_EXPIRY, time() + ($expiremins * 60 * 60));

			$this->releaseLock($key);
			return true;
		}

		/**
		 * Returns true if the specified key exists in the cache.
		 *
		 * @param string $key Key to check in the cache.
		 * @return bool True if key is in cache (and not expired), false otherwise.
		 */
		public function isCached($key)
		{
			return file_exists($this->getKeyFilename($key));
		}

		/**
		 * Removes (unsets) a value from the cache.
		 *
		 * @param string $key Key to remove from the cache.
		 */
		public function remove($key)
		{
			$filename = $this->getKeyFilename($key);
			// obtain the lock, in case some other process is writing
			$this->obtainLock($key); // don't care if it worked or not
			@unlink($filename);
			@unlink($filename . self::SUFFIX_METADATA);
			$this->releaseLock($key);
		}

		//
		// Implementation
		//

		/**
		 * Returns a full path filename for the specified key inside the cache.
		 * @param string $key
		 */
		private function getKeyFilename($key)
		{
			if(!isset($key) || ereg('.\\/~', $key))
				throw new Exception('DiskCache: Illegal key name');

			return sprintf('%s/%s.%s', $this->_location, strtolower($key), self::SUFFIX_DATA);
		}

		private function isKeyLocked($key)
		{
			$fname = $this->getKeyFilename($key) . self::SUFFIX_LOCK;
			return file_exists($fname);
		}

		private function obtainLock($key, $timeout = 15)
		{
			// wait to become available
			$filename = $this->getKeyFilename($key) . self::SUFFIX_LOCK;
			for($attempts = 0; $attempts < $timeout; $attempts++)
			{
				if(file_exists($filename))
					// block for 100ms
					usleep(100);
				else
					// create the lock file
					return touch($filename);
			}

			return false; // could not lock
		}

		private function releaseLock($key)
		{
			@unlink($this->getKeyFilename($key) . self::SUFFIX_LOCK);
		}

		private function getMetaData($key)
		{
			$metafile = $this->getKeyFilename($key) . self::SUFFIX_METADATA;
			$metadata = file_get_contents($metafile);
			if($metadata === false) // metadata doesn't exist for file
				$metadata = array();
			else
				$metadata = unserialize($metadata); // restore the array

			return $metadata;
		}

		private function setMetaData($key, $metakey, $metavalue)
		{
			$metadata = $this->getMetaData($key);
			// set the value
			$metadata[$metakey] = $metavalue;
			// write to disk
			file_put_contents($this->getKeyFilename($key) . self::SUFFIX_METADATA, serialize($metadata));
		}
	}
