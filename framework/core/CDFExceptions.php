<?php

class CDFNullReferenceException extends Exception
{
	public function __construct($message = '')
	{
		parent::__construct($message, 0, null);
	}
}

class CDFInvalidObjectException extends Exception
{
	public function __construct($message = '')
	{
		parent::__construct($message, 0, null);
	}
}

class CDFInvalidOperationException extends Exception
{
	public function __construct($message = '')
	{
		parent::__construct($message, 0, null);
	}
}

class CDFInvalidArgumentException extends Exception
{
	public function __construct($message = '')
	{
		parent::__construct($message, 0, null);
	}
}

/**
 * Thrown when a data object detects a value of a column is not correct for whatever reason (date out of range, etc)
 */
final class CDFColumnDataException extends Exception
{
	private $_key;

	public function __construct($columnKey, $message)
	{
		parent::__construct($message);
		$this->_key = $columnKey;
	}

	public function getColumnKey()
	{
		return $this->_key;
	}
}

/**
 * Defines a special exception for detecting database errors in the application.
 * Thrown by database access classes and should be caught by the application (use set_exception_handler).
 */
final class CDFSqlException extends Exception
{
	private $_query;

	/**
	 * @param string $msg
	 * @param string $query
	 * @param int $code
	 */
	function __construct($msg, $query = null, $code = -1)
	{
		parent::__construct($msg, $code, null);
		$this->_query = $query;
	}

	public function __toString()
	{
		return sprintf('%s (%s)\r\nTrace: %s\r\n',
			$this->message,
			$this->_query === null ? '???' : $this->_query,
			$this->getTraceAsString());
	}
}

final class CDFMailMessageException extends Exception
{

}


/**
 * Thrown whenever there is a failure during handling of a JSON request or response. Can also be thrown by application
 * implementations.
 */
final class CDFJsonException extends Exception
{
	public function __construct($msg, $errorCode)
	{
		parent::__construct($msg, $errorCode);
	}
}
