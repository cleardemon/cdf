<?php

	require_once 'CDFMySqlClient.php';
	require_once 'CDFDataObjectValidationError.php';

	abstract class CDFDataObject
	{
		const OPTION_ISREQUIRED = 'isrequired';
		const OPTION_NOTNULL = 'notnull';
		const OPTION_MAXLENGTH = 'maxlen';
		const OPTION_MINLENGTH = 'minlen';

		/**
		 * @var array
		 */
		private $_validationerrors;

		//
		// Columns
		//

		/**
		 * Array of columns
		 * @var array
		 */
		private $_columns;

		/**
		 * Defines the columns used for this data object.
		 * Column types:
		 * 'i' - integer
		 * 'f' - floating point
		 * 's' - varchar
		 * 't' - text
		 * 'b' - bit/boolean
		 * 'd' - datetime
		 * @param array $cols
		 * @example
		 * <code>
		 * class MyObject extends CDFDataObject
		 * {
		 *   function __construct()
		 *   {
		 *     $this->addColumns(array(
		 *       array('Column1', 'i'), // integer column
		 *       array('Column2', 'f'), // float column
		 *       array('Column3', 's'), // varchar column
		 *       array('Column4', 't') // text column
		 *       array('Column5', 's', array(OPTION_ISREQUIRED => true, OPTION_MAXLENGTH = 10))
		 *     ));
		 *   }
		 * }
		 * </code>
		 */
		final protected function addColumns($cols)
		{
			foreach($cols as $col)
			{
				if(sizeof($col) < 2)
					continue;
				$type = CDFSqlDataType::Integer;
				switch($col[1])
				{
					case 'f':
					case 'F':
						$type = CDFSqlDataType::Float;
						break;
					case 't':
					case 'T':
						$type = CDFSqlDataType::Text;
						break;
					case 's':
					case 'S':
						$type = CDFSqlDataType::String;
						break;
					case 'b':
					case 'B':
						$type = CDFSqlDataType::Bool;
						break;
					case 'd':
					case 'D':
						$type = CDFSqlDataType::Timestamp;
						break;
				}
				$this->setColumn($col[0], null, $type, array_key_exists(2, $col) ? $col[2] : null);
			}
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 * @param int $type Optional type of column (one of CDFIDataConnection's constants)
		 * @param array $options Defines options for the column, such as validation rules, etc
		 */
		private function setColumn($key, $value, $type = -1, $options = null)
		{
			if(!is_string($key))
				throw new Exception('Column key must be string');

			// make sure we have an array
			if($this->_columns == null)
				$this->_columns = array();

			// special case for dates
			if($type == CDFSqlDataType::Timestamp && !($value instanceof DateTime))
				$value = CDFDataHelper::AsDateTime($value);

			// set the value
			if(array_key_exists($key, $this->_columns))
			{
				$this->_columns[$key][0] = $value; // change the value
				// has type of column changed? (not something that should really happen)
				if($type != -1 && $type != $this->_columns[$key][1])
					throw new Exception('Columns cannot change type once set');
				if($options != null && is_array($options))
					$this->_columns[$key][2] = $options;
			}
			else
				// not set
				$this->_columns[$key] = array($value, $type, is_array($options) ? $options : null);
		}

		final protected function getColumnOption($key, $optionkey)
		{
			$column = $this->getColumn($key);
			// if column not found, or column is missing options, return null
			if($column == null || !isset($column[2]) || !is_array($column[2]))
				return null;
			// if option key exists return it, null otherwise
			return array_key_exists($optionkey, $column[2]) ? $column[2][$optionkey] : null;
		}

		/**
		 * Sets a value of a column to be a string, passing it to AsStringSafe first. Usable on column types string and text.
		 * @param string $key
		 * @param mixed $value
		 */
		final protected function setColumnString($key, $value)
		{
			// does not set a column type to be compatible with strings *and* text columns.
			$this->setColumn($key, CDFDataHelper::AsStringSafe($value));
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 */
		final protected function setColumnInteger($key, $value)
		{
			$this->setColumn($key, CDFDataHelper::AsInt($value), CDFSqlDataType::Integer);
		}

		/**
		 * @param string $key
		 * @param mixed $value
		 */
		final protected function setColumnFloat($key, $value)
		{
			$this->setColumn($key, CDFDataHelper::AsFloat($value), CDFSqlDataType::Float);
		}

		final protected function setColumnBoolean($key, $value)
		{
			$this->setColumn($key, CDFDataHelper::AsBool($value), CDFSqlDataType::Bool);
		}

		final protected function setColumnDateTime($key, $value)
		{
			$this->setColumn($key, CDFDataHelper::AsDateTime($value), CDFSqlDataType::Timestamp);
		}

		final protected function clearColumns()
		{
			$this->_columns = null;
		}

		/**
		 * @param string $key
		 * @return array
		 */
		private function getColumn($key)
		{
			if(!is_string($key))
				throw new Exception('Column key must be string');
			if($this->_columns == null)
				return null; // no columns anyway
			if(!array_key_exists($key, $this->_columns))
				return null; // not set in array

			return $this->_columns[$key];
		}

		/**
		 * @param string $key
		 * @param bool $allownull
		 * @return mixed Null if column not found.
		 */
		final protected function getColumnValue($key, $allownull = false)
		{
			$col = $this->getColumn($key);
			if($col == null)
				return null;
			// don't return null if not specified, based on column type
			if($col[0] === null && $allownull == false)
			{
				switch($col[1])
				{
					case CDFSqlDataType::String:
					case CDFSqlDataType::Text:
						return '';
					case CDFSqlDataType::Float;
						return 0.0;
					case CDFSqlDataType::Integer;
						return 0;
					case CDFSqlDataType::Bool;
						return false;
					case CDFSqlDataType::Timestamp;
						return CDFDataHelper::AsDateTime(null);
				}
			}
			return $col[0];
		}

		/**
		 * @param string $key
		 * @return int One of the CDFIDataConnection constants.
		 */
		final protected function getColumnType($key)
		{
			$col = $this->getColumn($key);
			return $col == null ? null : $col[1];
		}

		/**
		 * Adds columns to the current SQL query.
		 * Never adds any column called 'Id'.
		 * Note that column names will want to match those as in the database table otherwise this won't work as expected.
		 *
		 * @param CDFIDataConnection $db An open database connection
		 * @param array $keys  List of column keys to NOT add. If null, adds all.
		 * @return int Number of columns added to the query.
		 */
		final protected function addColumnsToParameters(CDFIDataConnection $db, $keys = null)
		{
			if($this->_columns == null || $db == null)
				return 0;

			$num = 0;
			foreach($this->_columns as $key => $value)
			{
				if($key == 'Id' || $value[1] == -1)
					continue; // skip columns with no set type
				if($keys != null && array_search($key, $keys) !== false)
					continue; // skip if specified in keys array
				$db->AddParameter($value[1], $value[0]);
				$num++;
			}

			return $num;
		}

		/**
		 * Loads values from an array, returned by mysql_fetch_assoc.
		 * @param array $rows
		 * @return bool True on success
		 */
		final protected function loadColumnValues($rows)
		{
			if($rows === false || count($rows) == 0)
				return false;

			foreach($this->_columns as $key => $value)
			{
				// find the value in the input array
				if(!array_key_exists($key, $rows))
					continue;
				$inputvalue = $rows[$key];
				// change it in the column, safely
				switch($value[1])
				{
					case CDFSqlDataType::Float:
						$this->setColumnFloat($key, $inputvalue);
						break;
					case CDFSqlDataType::Integer:
						$this->setColumnInteger($key, $inputvalue);
						break;
					case CDFSqlDataType::String:
					case CDFSqlDataType::Text:
						$this->setColumnString($key, $inputvalue);
						break;
					case CDFSqlDataType::Bool:
						$this->setColumnBoolean($key, $inputvalue);
						break;
					case CDFSqlDataType::Timestamp:
						$this->setColumnDateTime($key, $inputvalue);
						break;
				}
			}

			return true;
		}

		//
		// Validation
		//

		/**
		 * Adds a validation error to the list.
		 * @param string $key Column key.
		 * @param int $code Validation error type
		 * @param string|null $msg Custom message to display, if any.
		 */
		private function addValidationError($key, $code, $msg = null)
		{
			if($this->_validationerrors === null)
				throw new CDFInvalidOperationException('Initial validation not performed first');
			$err = new CDFDataObjectValidationError($key, $code);
			if($msg !== null)
				$err->setErrorDescription($msg);
			$this->_validationerrors[] = $err;
		}

		/**
		 * Performs validation on the values of the current columns.
		 * The validation to perform on each column is defined by the options associated with the column.
		 * This returns an array of CDFDataObjectValidationError classes for any errors found.
		 * Optionally specify an array of columns to check, otherwise it checks all columns.
		 * Note that any column explictly called 'Id' is always skipped.
		 *
		 * @param array $cols Column keys to check or null to check all.
		 * @param bool $stoponerror If true, returns immediately when one validation check fails.
		 * @return bool True if an error occured
		 */
		final public function doValidation($cols = null, $stoponerror = false)
		{
			$this->_validationerrors = array();

			foreach(array_keys($this->_columns) as $key)
			{
				if($key == 'Id') // always skip database Id fields
					continue;
				if($cols != null && !array_key_exists($key, $cols)) // skip?
					continue;

				// is null
				if($this->getColumnOption($key, self::OPTION_NOTNULL) == true)
				{
					if($this->getColumnValue($key, true) === null)
					{
						// column is null, shouldn't be
						$this->addValidationError($key, CDFValidationErrorCode::ValueCannotBeNull);
						if($stoponerror == true)
							break;

						// prevent validation for this column continuing if it shouldn't be null
						continue;
					}
				}

				$type = $this->getColumnType($key);
				$value = $this->getColumnValue($key);

				// is required
				if($this->getColumnOption($key, self::OPTION_ISREQUIRED) == true)
				{
					$fail = false;
					switch($type)
					{
						case CDFSqlDataType::String:
						case CDFSqlDataType::Text:
							$fail = strlen($value) == 0;
							break;
						case CDFSqlDataType::Timestamp:
							// if epoch, not specified
							$fail = CDFDataHelper::AsDateTime($value)->getTimestamp() == 0;
							break;
					}

					if($fail != false)
					{
						$this->addValidationError($key, CDFValidationErrorCode::ValueIsNotSet);
						if($stoponerror == true)
							break;
					}
				}

				// string operations
				if($type == CDFSqlDataType::String || $type == CDFSqlDataType::Text)
				{
					// max length
					$optvalue = $this->getColumnOption($key, self::OPTION_MAXLENGTH);
					if($optvalue !== null && strlen($value) > $optvalue)
					{
						$this->addValidationError($key, CDFValidationErrorCode::ValueLengthTooLong);
						if($stoponerror == true)
							break;
					}

					// min length
					$optvalue = $this->getColumnOption($key, self::OPTION_MINLENGTH);
					if($optvalue !== null && strlen($value) < $optvalue && strlen($value) !== 0)
					{
						$this->addValidationError($key, CDFValidationErrorCode::ValueLengthTooShort);
						if($stoponerror == true)
							break;
					}
				}
			}

			// do local validation - implementation should add a validation error, on errors
			$this->localValidation();

			return $this->hasValidationErrors();
		}

		/**
		 * Allows for an implementation of the CDFDataObject to perform additional validation checks.
		 * Override to do this.
		 */
		protected function localValidation()
		{
		}

		/**
		 * Returns true if there are problems with the specified data for this object.
		 * @return bool True if there are errors, false if everything OK.
		 */
		final public function hasValidationErrors()
		{
			return count($this->_validationerrors) > 0 ? true : false;
		}

		/**
		 * Returns a list of errors since the last call to doValidation.
		 * @return array Zero or more CDFDataObjectValidationError's
		 */
		final public function getValidationErrors()
		{
			return $this->_validationerrors;
		}

		/**
		 * Adds a custom validation error to the list.  Must be called after a regular call to doValidation.
		 * @param string $column Column key that is at fault.
		 * @param string $msg Human-readable message to why this is at fault.
		 */
		final protected function addCustomValidationError($column, $msg)
		{
			$this->addValidationError($column, CDFValidationErrorCode::CustomError, $msg);
		}

		//
		// Implementation classes
		// MUST be overridden.
		//

		public function Create()
		{
			throw new Exception('Create object not supported');
		}

		public function Update()
		{
			throw new Exception('Update object not supported');
		}

		public function Delete()
		{
			throw new Exception('Delete object not supported');
		}

		public function Undelete()
		{
			throw new Exception('Undelete object not supported');
		}

		//
		// Query helpers
		//

		/**
		 * Gets a list of column names to use in a query, skipping ones supplied.
		 * @param array|null $skipkeys Columns to skip, or null to add all.
		 * @return array List of columns.
		 */
		private function getColumnNames($skipkeys = null)
		{
			$cols = array();
			foreach(array_keys($this->_columns) as $key)
			{
				// Always skip 'Id' column
				if($key == 'Id' || ($skipkeys != null && array_search($key, $skipkeys) !== false))
					continue; // skip if specified in keys array
				$cols[] = $key;
			}
			return $cols;
		}

		/**
		 * Performs an INSERT INTO query on the supplied table using the parameters added to the query.
		 * <code>
		 *   $foo->addColumns(array(array('foo', 's'), array('bar', 'i')));
		 *   $foo->setColumnString('foo', 'abc');
		 *   $foo->setColumnInteger('bar', 12345);
		 *   $foo->addColumnsToParameters($db); // where db is an open data connection
		 *   $foo->queryInsertInto($db, 'table');
		 * </code>
		 * This example would execute the following SQL:
		 *   insert into table (foo,bar) values ('abc',12345)
		 *
		 * @param CDFIDataConnection $db Open database connection
		 * @param string $tablename Name of the table to use.
		 * @param array|null $skipkeys List of columns to skip adding to the query.
		 */
		final protected function queryInsertInto(CDFIDataConnection $db, $tablename, $skipkeys = null)
		{
			// get the column names
			$cols = $this->getColumnNames($skipkeys);

			// create the sql
			$values = substr(str_repeat(CDFIDataConnection_TokenCharacter . ',', count($cols)), 0, -1); // trim off last comma
			$sql = sprintf('insert into %s (%s) values (%s)', $tablename, implode(',', $cols), $values);

			// pass the query to the processor
			$db->Query($sql);
		}

		/**
		 * Performs an UPDATE query on the supplied table, using the current columns.
		 * @param CDFIDataConnection $db Database connection
		 * @param string $tablename Name of the table to use
		 * @param string $reqcol Column that should be used as a requisite for the query (where)
		 * @param array|null $skipkeys List of columns to skip
		 */
		final protected function queryUpdate(CDFIDataConnection $db, $tablename, $reqcol, $skipkeys = null)
		{
			// build list of setters
			$sets = array();
			foreach($this->getColumnNames($skipkeys) as $key)
				$sets[] = $key . '=' . CDFIDataConnection_TokenCharacter; // foo=?

			// append an additional parameter for the where clause
			$where = $this->getColumn($reqcol);
			$db->AddParameter($where[1], $where[0]);

			// pass query on to processor
			$db->Query(sprintf('update %s set %s where %s=' . CDFIDataConnection_TokenCharacter, $tablename, implode(',', $sets), $reqcol));
		}
	}
