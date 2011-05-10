<?php

require_once dirname(__FILE__) . '/../core/CDFExceptions.php';
require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';

/**
 * Defines a list of errors that can occur either in the framework or otherwise. Applications are free to use
 * these identifiers during their own parsing. Used as a value for CDFJsonException.
 */
interface CDFJsonErrorCode
{
	const InvalidRequest = 1; // server cannot understand request
	const ParseError = 2; // error in JSON or not actually JSON
}

/**
 * Handles mid-level parsing of JSON requests.
 * @throws CDFInvalidOperationException|CDFJsonException
 */
class CDFJsonRequest
{
	/** @var array */
	private $_jsonData;
	/** @var bool */
	private $_parsed;

	/**
	 * Retrieves a value from the parsed data.
	 * @param string $key
	 * @return mixed|null
	 * @throws CDFInvalidOperationException
	 */
	public function getValueByKey($key)
	{
		if(!$this->_parsed)
			throw new CDFInvalidOperationException();
		if($this->_jsonData == null || !isset($this->_jsonData[$key]))
			return null;

		return $this->_jsonData[$key];
	}

	/**
	 * Returns true if the specified array of keys is present in the parsed data.
	 * @param array $keys
	 * @return bool
	 * @throws CDFInvalidOperationException
	 */
	public function hasKeys($keys)
	{
		if(!$this->_parsed)
			throw new CDFInvalidOperationException();
		if(!is_array($keys))
			$keys = func_get_args();
		return CDFDataHelper::hasArrayKeys($this->_jsonData, $keys);
	}

	/**
	 * Retrieves a value from the parsed data, by numeric index.
	 * @param int $index
	 * @return mixed|null
	 * @throws CDFInvalidOperationException
	 */
	public function getValueByIndex($index)
	{
		if(!$this->_parsed)
			throw new CDFInvalidOperationException();
		$index = CDFDataHelper::AsInt($index);
		if($this->_jsonData == null || !isset($this->_jsonData[$index]))
			return null;

		return $this->_jsonData[$index];
	}

	/**
	 * Returns the number of top-level items in the parsed data.
	 * @throws CDFInvalidOperationException
	 * @return int
	 */
	public function getSize()
	{
		if(!$this->_parsed)
			throw new CDFInvalidOperationException();

		return count($this->_jsonData);
	}

	/**
	 * Parses the request from the raw HTTP POST body. Note that it must be sent with the correct content type header.
	 * @return void
	 * @throw CDFJsonException
	 */
	final public function loadFromPOST()
	{
		$this->_parsed = false;
		if($_SERVER['REQUEST_METHOD'] != 'POST')
			throw new CDFJsonException('Incorrect protocol', CDFJsonErrorCode::InvalidRequest);
		if(!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/json')
			throw new CDFJsonException('Expecting JSON', CDFJsonErrorCode::InvalidRequest);

		// parse untouched post data
		$this->parse(file_get_contents('php://input'));
	}

	/**
	 * Parses the request from the supplied string.
	 * @param string $input
	 * @return bool
	 */
	final public function loadFromString($input)
	{
		$this->_parsed = false;
		if(!is_string($input))
			return false;

		$this->parse($input);
		return true;
	}

	/**
	 * Override in application implementation to do additional parsing. Parsed data is available when this method
	 * is called.
	 * @return void
	 */
	protected function parseCallback()
	{
	}

	/**
	 * Meat of the parser.
	 * @throws JsonException
	 * @param string $input
	 * @return void
	 */
	private function parse($input)
	{
		// attempt to load as JSON
		$this->_jsonData = json_decode($input, true, 16);
		if(json_last_error() !== JSON_ERROR_NONE)
		{
			switch(json_last_error())
			{
				case JSON_ERROR_UTF8:
					throw new CDFJsonException('Malformed UTF-8 characters, possibly incorrectly encoded', CDFJsonErrorCode::ParseError);
				case JSON_ERROR_DEPTH:
					throw new CDFJsonException('The maximum stack depth has been exceeded', CDFJsonErrorCode::ParseError);
				case JSON_ERROR_CTRL_CHAR:
					throw new CDFJsonException('Control character error, possibly incorrectly encoded', CDFJsonErrorCode::ParseError);
				case JSON_ERROR_SYNTAX:
					throw new CDFJsonException('Syntax error', CDFJsonErrorCode::ParseError);
				case JSON_ERROR_STATE_MISMATCH:
					throw new CDFJsonException('Invalid or malformed JSON', CDFJsonErrorCode::ParseError);
				default:
					throw new CDFJsonException('Unidentified JSON error', CDFJsonErrorCode::ParseError);
			}
		}

		// call the implementation
		$this->_parsed = true;
		$this->parseCallback();
	}

	/**
	 * Returns true to an implementation if a parse has occurred.
	 * @return bool
	 */
	protected function isParsed()
	{
		return $this->_parsed;
	}
}

/**
 * Allows for sending responses in JSON.
 */
class CDFJsonResponse
{
	/** @var array */
	private $_jsonData = array();

	/**
	 * Sets a value in the response.
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function setValue($key, $value)
	{
		$this->_jsonData[$key] = $value;
	}

	/**
	 * Returns a value already set for the response. Null if not set.
	 * @param string $key
	 * @return array|null
	 */
	public function getValue($key)
	{
		return isset($this->_jsonData[$key]) ? $this->_jsonData[$key] : null;
	}

	/**
	 * Returns the response as a JSON encoded string. Implementations can override this to tailor the output.
	 * @return string
	 */
	public function toJSON()
	{
		return json_encode($this->_jsonData);
	}

	/**
	 * Renders the response directly to the output (e.g. HTTP).
	 * @return void
	 * @throws CDFInvalidOperationException
	 */
	final public function toOutput()
	{
		if(headers_sent())
			throw new CDFInvalidOperationException();
		header('Content-Type: application/json');
		header('Expires: -1');

		echo $this->toJSON();
	}
}
