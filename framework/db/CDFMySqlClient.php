<?php
	/**
	 * Description of CDFMySqlClient
	 *
	 * @author demon
	 */

	require_once 'db/CDFIDataConnection.php';

	final class CDFMySqlClient implements CDFIDataConnection
	{
		private $params;
		private $handle = null;
		private $_credentials;

		function  __construct($credentials)
		{
			if(sizeof($credentials) == 0)
				throw new InvalidArgumentException('Missing SQL credentials');

			$this->NewQuery();
			$this->_credentials = $credentials;
		}

		function  __destruct()
		{
			$this->Close();
		}

		public function Open()
		{
			// connect to mysql database
			$this->handle = @mysql_connect($this->_credentials['hostname'], $this->_credentials['username'], $this->_credentials['password']);
			if($this->handle === false)
				throw new CDFSqlException(mysql_error(), '', mysql_errno());
			mysql_select_db($this->_credentials['database'], $this->handle);
		}

		public function Close()
		{
			// close handle
			if($this->HasConnection() && is_resource($this->handle))
				mysql_close($this->handle);
			$this->handle = null;
		}

		public function HasConnection()
		{
			return $this->handle !== null && $this->handle !== false;
		}

		public function AddParameter($type, $value)
		{
			// sanitise value
			switch($type)
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
				default:
					throw new CDFSqlException('CDFMySqlClient: invalid data type');
			}

			// add to list
			$this->params[] = array($type, $value);
		}

		public function NewQuery()
		{
			$this->params = array();
		}

		private function FormatValue($type, $value)
		{
			switch($type)
			{
				case CDFSqlDataType::String:
				case CDFSqlDataType::Text:
					return sprintf("'%s'", mysql_real_escape_string($value));
				case CDFSqlDataType::Integer:
					return sprintf("%d", $value);
				case CDFSqlDataType::Float:
					return sprintf("%F", $value); // non-locale aware float format
				case CDFSqlDataType::Bool:
					return $value === true ? '1' : '0';
				case CDFSqlDataType::Timestamp:
					// handle epoch times to be passed as null
					if($value == null || $value->getTimestamp() == 0)
						return 'NULL';
					return sprintf("'%s'", $value->format('Y-m-d H:i:s')); // format DateTime object to sql
			}

			throw new CDFSqlException('CDFMySqlClient: unknown data type in parameter build');
		}

		private function Execute($sql)
		{
			if(!$this->HasConnection())
				throw new CDFSqlException('Cannot execute query as connection not open', $sql);

			// execute query on database
			$this->Open(); // refreshes the handle
			$query = mysql_query($sql, $this->handle);
			if($query === false)
				// query errors, throw a CDFSqlException
				throw new CDFSqlException(mysql_error($this->handle), $sql, mysql_errno($this->handle));

			// query succeeded, get rows, if any
			$rows = array();
			if(is_resource($query))
			{
				// query returned rows/columns
				if(mysql_num_rows($query) > 0)
				{
					// there are rows, read into arrays
					while($row = mysql_fetch_assoc($query))
						$rows[] = $row;
				}
				mysql_free_result($query);
			}

			// return an array, regardless of columns/rows, to signify success.
			return $rows;
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
			$sql = sprintf('call %s', $name);
			if(count($this->params) > 0)
			{
				// parameters available, append each one sequentially to the query
				$parts = array();
				foreach($this->params as $type => $value)
					$parts[] = $this->FormatValue($type, $value);

				// append parts to the query
				$sql .= ' ' . implode(', ', $parts);
			}

			// run the query
			return $this->Execute($sql);
		}

		/**
		 * Executes an inline SQL query on the database.
		 * Will throw a CDFSqlException if database fails for some reason.
		 *
		 * <code>
		 *	$db->Query('select * from table'); // standard query
		 *
		 *  $db->AddParameter(CDFSqlDataType::String, 'foo');
		 *  $db->AddParameter(CDFSqlDataType::Integer, 12345);
		 *  $db->Query('select * from Users where Username=? AND Type=?');
		 *    // this expands to: select * from Users where Username='foo' AND Type=12345
		 * </code>
		 * @param $sql string SQL query to execute.
		 * @return array Array of rows/columns or false on failure.
		 */
		public function Query($sql)
		{
			// format parameters
			$parampos = 0;
			$paramcount = count($this->params);
			$tokenpos = 0;
			for(;;)
			{
				// find first/next occurance of token
				$tokenpos = strpos($sql, CDFIDataConnection_TokenCharacter, $tokenpos);
				if($tokenpos === false)
					break; // no more tokens

				// check if we're within the number of passed parameters
				if($parampos == $paramcount)
					throw new CDFSqlException('CDFMySqlClient: too many parameters passed in query', $sql);

				// replace token with value of parameter
				$param = $this->params[$parampos];
				$sql = substr_replace($sql, $this->FormatValue($param[0], $param[1]), $tokenpos, 1);

				$parampos++; // next parameter
			}

			if($parampos != $paramcount)
				throw new CDFSqlException("CDFMySqlClient: not enough parameters passed to query (expecting $paramcount, got $parampos)", $sql);

			// execute sql
			return $this->Execute($sql);
		}

		/**
		 * Returns the last auto-increment number assigned by the last insert query.
		 * @return int The last number created by the database.
		 */
		public function LastID()
		{
			$rows = $this->Execute('select last_insert_id() as Id');
			return CDFDataHelper::AsInt($rows[0]['Id']);
		}
	}
