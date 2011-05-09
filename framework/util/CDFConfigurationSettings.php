<?php

require_once dirname(__FILE__) . '/../core/CDFExceptions.php';

/**
 * Parses an ini file located at the root of the application, retrieving values from it.
 * @throws CDFInvalidArgumentException
 */
final class CDFConfigurationSettings
{
	/** @var array */
	private $_parsedIni = null;
	/** @var string */
	private $_configFile = null;


	/**
	 * Tells this instance of configuration settings where the .ini file is. Throws exception if not found.
	 * @param string $file
	 * @return void
	 * @throws CDFInvalidArgumentException
	 */
	public function setConfigFile($file)
	{
		if(!file_exists($file))
			throw new CDFInvalidArgumentException('Configuration file not found');
		$this->_configFile = $file;
	}

	/**
	 * Parses the ini file.
	 * @throws CDFInvalidOperationException
	 * @return void
	 */
	private function parseIni()
	{
		// attempt to load the ini file
		if($this->_parsedIni === null)
		{
			$ini = parse_ini_file($this->_configFile, true);
			if($ini === false)
				throw new CDFInvalidOperationException('Configuration file could not be loaded');

			$this->_parsedIni = $ini;
		}
	}

	/**
	 * Retrieves an entire named section from the ini file. Null on failure.
	 * @param string $name
	 * @return array|null
	 */
	public function getSection($name)
	{
		$this->parseIni();

		// attempt to find the section
		if(!isset($this->_parsedIni[$name]))
			return null;

		return $this->_parsedIni[$name];
	}

	/**
	 * Retrieves an individual value from a section. Null on failure.
	 * @param string $section
	 * @param string $key
	 * @return string|null
	 */
	public function getValue($section, $key)
	{
		// get the section
		$s = $this->getSection($section);
		if($s === null)
			return null;

		return isset($s[$key]) ? $s[$key] : null;
	}
}
