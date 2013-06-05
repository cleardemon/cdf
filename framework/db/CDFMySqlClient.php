<?php
/**
 * Implementation of a MySQL client adhering to the CDFIDataConnection interface.
 * @package CDF
 */

// PRO TIP: If you define('CDF_SQL_DEBUG'), messages about the SQL queries this class generates will be written to syslog.

require_once dirname(__FILE__) . '/../core/CDFExceptions.php';
require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';
require_once 'CDFIDataConnection.php';

/**
 * MySQL client.
 * @throws CDFSqlException|InvalidArgumentException
 */
final class CDFMySqlClient implements CDFIDataConnection
{
	/** @var array */
	private $_params;
	/** @var mysqli */
	private $_handle = null;
	/** @var array */
	private $_credentials;
	/** @var int */
	private $_lastRowCount = 0;
	/** @var mysqli_result */
	private $_lastQuery = null;

	/**
	 * Creates a new instance of this class, with database credentials.
	 * @throws InvalidArgumentException
	 * @param array $credentials An array containing keyed items: hostname, username, password, database
	 */
	function  __construct($credentials)
	{
		if (sizeof($credentials) == 0)
			throw new InvalidArgumentException('Missing SQL credentials');

		$this->NewQuery();
		$this->_credentials = $credentials;
	}

	function  __destruct()
	{
		$this->Close();
	}

	/**
	 * Opens a connection to the supplied MySQL database.
	 * @throws CDFSqlException
	 * @return void
	 */
	public function Open()
	{
		// connect to mysql database
		$this->_handle = new mysqli($this->_credentials['hostname'], $this->_credentials['username'], $this->_credentials['password'], $this->_credentials['database']);
		if($this->_handle->errno)
		{
			$error = $this->_handle->error;
			$number = $this->_handle->errno;
			$this->Close();
			throw new CDFSqlException($error, null, $number);
		}
		$this->_handle->set_charset('utf8');
	}

	/**
	 * Closes the connection to the database. Safe to call if not connected.
	 * @return void
	 */
	public function Close()
	{
		// close handles
		if($this->_lastQuery != null)
			$this->_lastQuery->close();
		if($this->HasConnection() && $this->_handle != null)
			$this->_handle->close();
		$this->_handle = null;
		$this->_lastQuery = null;
	}

	/**
	 * Returns true if there is a current connection to the database.
	 * @return bool
	 */
	public function HasConnection()
	{
		return $this->_handle !== null && $this->_handle->connect_errno == 0;
	}

	/**
	 * Adds a new parameter for use in a query.
	 * @throws CDFSqlException
	 * @param int $type One value from CDFSqlDataType
	 * @param mixed $value Content of the parameter.
	 * @return void
	 */
	public function AddParameter($type, $value)
	{
		if($value !== null)
		{
			// sanitise value
			switch ($type)
			{
				case CDFSqlDataType::String:
					$value = CDFDataHelper::AsStringSafe($value);
					break;
				case CDFSqlDataType::Integer:
					$value = CDFDataHelper::AsInt($value);
					break;
				case CDFSqlDataType::Float:
					$value = CDFDataHelper::AsFloat($value);
					break;
				case CDFSqlDataType::Text:
					$value = CDFDataHelper::AsStringSafe($value, false); // preserve html for text blocks
					break;
				case CDFSqlDataType::Timestamp:
					$value = CDFDataHelper::AsDateTime($value);
					break;
				case CDFSqlDataType::Bool:
					$value = CDFDataHelper::AsBool($value);
					break;
				case CDFSqlDataType::Data:
					$value = CDFDataHelper::AsString($value); // no safe conversion at all
					break;
				default:
					throw new CDFSqlException('CDFMySqlClient: invalid data type');
			}
		}

		// add to list
		$this->_params[] = array($type, $value);
	}

	/**
	 * Resets the state of this connection, ready to execute a new query.
	 * @return void
	 */
	public function NewQuery()
	{
		$this->_params = array();
		$this->_lastRowCount = 0;
		if($this->_lastQuery != null)
		{
			@$this->_lastQuery->close();
			$this->_lastQuery = null;
		}
	}

	const ValueMagicCharacter = '\x1A'; // chr(26)

	/**
	 * Formats a value appropriately for use in a MySQL query.
	 * @throws CDFSqlException
	 * @param int $type
	 * @param mixed $value
	 * @param bool $changedValue
	 * @return string
	 */
	private function FormatValue($type, $value, &$changedValue)
	{
		$changedValue = false;
		switch ($type)
		{
			case CDFSqlDataType::String:
			case CDFSqlDataType::Text:
			case CDFSqlDataType::Data: // blob data is a string in php
			{
				if($value === null)
					return 'NULL';
				// if value has an occurrence of the token, escape it out to something that can't be typed
				$count = 0;
				$value = str_replace(CDFIDataConnection_TokenCharacter, self::ValueMagicCharacter, $value, $count);
				if($count > 0)
					// caller MUST remove the magic character before passing to SQL!
					$changedValue = true;
				return sprintf("'%s'", $this->_handle->real_escape_string($value));
			}
			case CDFSqlDataType::Integer:
				if($value === null)
					return 'NULL';
				return sprintf("%d", $value);
			case CDFSqlDataType::Float:
				if($value === null)
					return 'NULL';
				return sprintf("%F", $value); // non-locale aware float format
			case CDFSqlDataType::Bool:
				if($value === null)
					return 'NULL';
				return $value === true ? '1' : '0';
			case CDFSqlDataType::Timestamp:
				// handle epoch times to be passed as null
				/** @var $value DateTime */
				if ($value === null || $value->getTimestamp() == 0)
					return 'NULL';
				$value->setTimezone(new DateTimeZone('GMT'));
				return sprintf("'%s'", $value->format('Y-m-d H:i:s')); // format DateTime object to sql
		}

		throw new CDFSqlException('Unknown data type in parameter build');
	}

	/**
	 * Executes the supplied raw SQL query.
	 * @throws CDFSqlException
	 * @param string $sql
	 * @param bool $readAll If true, reads all rows from the query into an array, otherwise leaves the query open for reading via fetchNextRow().
	 * @return array
	 */
	private function Execute($sql, $readAll = false)
	{
		if (!$this->HasConnection())
			throw new CDFSqlException('Cannot execute query as connection not open', $sql);

		// dispose any hanging query
		$this->_lastRowCount = 0;
		if($this->_lastQuery != null)
		{
			@$this->_lastQuery->close();
			$this->_lastQuery = null;
		}

		if(defined('CDF_SQL_DEBUG'))
			syslog(LOG_DEBUG, "CDFMySqlClient: Executing - $sql");

		// execute query on database
		if(!$this->HasConnection())
			$this->Open(); // attempt to reconnect if lost
		$query = $this->_handle->query($sql, $readAll ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT);
		if($query === false)
			// query errors, throw a CDFSqlException
			throw new CDFSqlException($this->_handle->error, $sql, $this->_handle->errno);

		// query succeeded, get rows, if told to via readAll
		$rows = array();
		if($query === true)
			$this->_lastRowCount = $this->_handle->affected_rows;
		else
		{
			// query returned rows/columns
			$this->_lastRowCount = $query->num_rows;
			if ($this->_lastRowCount > 0 && $readAll)
			{
				// there are rows, read into array
				while($row = $query->fetch_assoc())
					$rows[] = $row;
				// dispose the query handle if reading all
				$query->close();
			}
			else
				// keep reference to query result for iterative reading
				$this->_lastQuery = $query;
		}

		// return an array, regardless of columns/rows, to signify success.
		return $rows;
	}

	private function resolveProcedure($sql)
	{
		if (count($this->_params) > 0)
		{
			// parameters available, append each one sequentially to the query
			$parts = array();
			$changed = false;
			foreach ($this->_params as $type => $value)
			{
				$didChange = false;
				$parts[] = $this->FormatValue($type, $value, $didChange);
				if($didChange == true)
					$changed = true;
			}

			// append parts to the query
			$sql .= ' ' . implode(', ', $parts);

			// remove magic escaping
			if($changed)
				$sql = str_replace(self::ValueMagicCharacter, CDFIDataConnection_TokenCharacter, $sql);
		}
		return $sql;
	}

	/**
	 * Executes the specified stored procedure on the database.
	 * Will throw a CDFSqlException if database fails for some reason.
	 * @param string $name Procedure name to execute
	 * @return array Array of rows/columns or false on failure.
	 */
	public function Procedure($name)
	{
		// build the sql required to call the procedure (lame)
		$sql = $this->resolveProcedure(sprintf('call `%s`', $name));
		// run the query
		return $this->Execute($sql, true);
	}

	/**
	 * Executes a stored procedure, but does not return results in a single array. Results are iterated in a call to NextRow().
	 * @param string $name Name of the stored procedure to execute.
	 * @return int Number of rows returned by execution of the procedure.
	 */
	public function BeginProcedure($name)
	{
		// build the sql required to call the procedure
		$sql = $this->resolveProcedure(sprintf('call `%s`', $name));
		// run the query
		$this->Execute($sql, false);
		return $this->_lastRowCount;
	}

	private function resolveParameters($sql)
	{
		$hasMagicChar = false;
		// format parameters
		$paramPosition = 0;
		$paramCount = count($this->_params);
		$tokenPosition = 0;
		for (; ;)
		{
			// find first/next occurrence of token
			$tokenPosition = strpos($sql, CDFIDataConnection_TokenCharacter, $tokenPosition);
			if ($tokenPosition === false)
				break; // no more tokens

			// check if we're within the number of passed parameters
			if ($paramPosition == $paramCount)
				throw new CDFSqlException('Too many parameters passed in query', $sql);

			// replace token with value of parameter
			$param = $this->_params[$paramPosition];
			$didChange = false;
			$sql = substr_replace($sql, $this->FormatValue($param[0], $param[1], $didChange), $tokenPosition, 1);
			if($didChange)
				$hasMagicChar = true;

			$paramPosition++; // next parameter
		}

		if ($paramPosition != $paramCount)
			throw new CDFSqlException("Not enough parameters passed to query (expecting $paramCount, got $paramPosition)", $sql);

		// remove the magic characters if any, returning the question marks
		if($hasMagicChar)
			$sql = str_replace(self::ValueMagicCharacter, CDFIDataConnection_TokenCharacter, $sql);

		return $sql;
	}

	/**
	 * Executes an inline SQL query on the database.
	 * Will throw a CDFSqlException if database fails for some reason.
	 *
	 * <code>
	 *    $db->Query('select * from table'); // standard query
	 *
	 *  $db->AddParameter(CDFSqlDataType::String, 'foo');
	 *  $db->AddParameter(CDFSqlDataType::Integer, 12345);
	 *  $db->Query('select * from Users where Username=? AND Type=?');
	 *    // this expands to: select * from Users where Username='foo' AND Type=12345
	 * </code>
	 * @param $sql string SQL query to execute.
	 * @param bool $skipParameters If true, do not process ? in queries. Advanced use.
	 * @throws CDFSqlException
	 * @return array Array of rows/columns or false on failure.
	 */
	public function Query($sql, $skipParameters = false)
	{
		if(!$skipParameters)
			$sql = $this->resolveParameters($sql);

		// execute sql
		return $this->Execute($sql, true);
	}

	/**
	 * Executes a SQL query, but does not return results in a single array. Results are iterated in a call to NextRow().
	 * @param string $sql SQL to execute.
	 * @param bool $skipParameters If true, do not process ? in queries. Advanced use.
	 * @return int Number of rows returned by query.
	 */
	public function BeginQuery($sql, $skipParameters = false)
	{
		if(!$skipParameters)
			$sql = $this->resolveParameters($sql);

		$this->Execute($sql, false);
		return $this->_lastRowCount;
	}

	/**
	 * Fetches the next row from the last run query, from either BeginQuery or BeginProcedure. Returns false when no more rows.
	 * @return array|bool
	 */
	public function NextRow()
	{
		if($this->_lastQuery != null)
			return $this->_lastQuery->fetch_assoc();
		return false;
	}

	/**
	 * Returns the last auto-increment number assigned by the last insert query.
	 * @throws CDFInvalidOperationException
	 * @return int The last number created by the database.
	 */
	public function LastID()
	{
		if(!$this->HasConnection())
			throw new CDFInvalidOperationException('No connection to MySQL server');
		return CDFDataHelper::AsInt($this->_handle->insert_id);
	}

	/**
	 * Returns the number of affected rows from the last query.
	 * @return int
	 */
	public function getAffectedRowCount()
	{
		return $this->_lastRowCount;
	}

	/**
	 * @param string $input
	 * @return string
	 * @throws CDFSqlException
	 */
	public function escapeVariable($input)
	{
		if(!$this->HasConnection())
			throw new CDFSqlException('Cannot escape input as connection not open');

		return $this->_handle->real_escape_string($input);
	}
}
