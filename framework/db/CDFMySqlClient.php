<?php
/**
 * Implementation of a MySQL client adhering to the CDFIDataConnection interface.
 * @package CDF
 */

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
	/** @var resource */
	private $_handle = null;
	/** @var array */
	private $_credentials;
	/** @var int */
	private $_lastRowCount = 0;
	/** @var resource */
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
		$this->_handle = @mysql_connect($this->_credentials['hostname'], $this->_credentials['username'], $this->_credentials['password']);
		if ($this->_handle === false)
			throw new CDFSqlException(mysql_error(), '', mysql_errno());
		mysql_select_db($this->_credentials['database'], $this->_handle);
		mysql_set_charset('utf8', $this->_handle);
	}

	/**
	 * Closes the connection to the database. Safe to call if not connected.
	 * @return void
	 */
	public function Close()
	{
		// close handles
		if($this->_lastQuery != null)
			mysql_free_result($this->_lastQuery);
		if ($this->HasConnection() && is_resource($this->_handle))
			mysql_close($this->_handle);
		$this->_handle = null;
		$this->_lastQuery = null;
	}

	/**
	 * Returns true if there is a current connection to the database.
	 * @return bool
	 */
	public function HasConnection()
	{
		return $this->_handle !== null && $this->_handle !== false;
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
			@mysql_free_result($this->_lastQuery);
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
				// if value has an occurrence of the token, escape it out to something that can't be typed
				$count = 0;
				$value = str_replace(CDFIDataConnection_TokenCharacter, self::ValueMagicCharacter, $value, $count);
				if($count > 0)
					// caller MUST remove the magic character before passing to SQL!
					$changedValue = true;
				return sprintf("'%s'", mysql_real_escape_string($value));
			}
			case CDFSqlDataType::Integer:
				return sprintf("%d", $value);
			case CDFSqlDataType::Float:
				return sprintf("%F", $value); // non-locale aware float format
			case CDFSqlDataType::Bool:
				return $value === true ? '1' : '0';
			case CDFSqlDataType::Timestamp:
				// handle epoch times to be passed as null
				/** @var $value DateTime */
				if ($value == null || $value->getTimestamp() == 0)
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
			mysql_free_result($this->_lastQuery);
			$this->_lastQuery = null;
		}

		// execute query on database
		$this->Open(); // refreshes the handle
		$query = mysql_query($sql, $this->_handle);
		if ($query === false)
			// query errors, throw a CDFSqlException
			throw new CDFSqlException(mysql_error($this->_handle), $sql, mysql_errno($this->_handle));

		// query succeeded, get rows, if any
		$rows = array();
		if (is_resource($query)) {
			$this->_lastRowCount = mysql_num_rows($query);

			// query returned rows/columns
			if ($this->_lastRowCount > 0 && $readAll) {
				// there are rows, read into array
				while ($row = mysql_fetch_assoc($query))
					$rows[] = $row;
				// dispose the query handle if reading all
				mysql_free_result($query);
			}
			else
				// keep reference to query result for iterative reading
				$this->_lastQuery = $query;
		}
		elseif ($query === true)
		{
			$this->_lastRowCount = mysql_affected_rows($this->_handle);
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
			return mysql_fetch_assoc($this->_lastQuery);
		return false;
	}

	/**
	 * Returns the last auto-increment number assigned by the last insert query.
	 * @return int The last number created by the database.
	 */
	public function LastID()
	{
		$rows = $this->Execute('select last_insert_id() as Id', true);
		return CDFDataHelper::AsInt($rows[0]['Id']);
	}

	/**
	 * Returns the number of affected rows from the last query.
	 * @return int
	 */
	public function getAffectedRowCount()
	{
		return $this->_lastRowCount;
	}

	public function escapeVariable($input)
	{
		if(!$this->HasConnection())
			throw new CDFSqlException('Cannot escape input as  connection not open');

		return mysql_real_escape_string($input, $this->_handle);
	}
}
