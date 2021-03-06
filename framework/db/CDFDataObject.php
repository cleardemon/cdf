<?php

	require_once 'CDFMySqlClient.php';
	require_once 'CDFDataObjectValidationError.php';
	require_once 'CDFDataColumn.php';

	abstract class CDFDataObject
	{
		const IgnoreColumnIdentity = 'Id'; // defines the name of an identity column, commonly used in table design

		/**
		 * @var CDFDataObjectValidationError[]
		 */
		private $_validationErrors;

		//
		// Columns
		//

		/**
		 * Array of columns
		 * @var CDFDataColumn[]
		 */
		private $_columns = null;

		/** @var string */
		private $_tableName = null; // name of the table that this object represents in a data store

		/**
		 * Defines the columns used for this data object.
		 * @param CDFDataColumn[] $cols
		 * @example
		 * <code>
		 * class MyObject extends CDFDataObject
		 * {
		 *   function __construct()
		 *   {
		 *     $this->addColumns(
		 * 		new CDFDataColumnInteger('Column1'), // integer column
		 * 		new CDFDataColumnFloat('Column2'), // float column
		 * 		new CDFDataColumnString('Column3', 'Default string!'), // varchar column
		 * 		new CDFDataColumnText('Column4'), // text column
		 * 		new CDFDataColumnString('Column5', null, array(CDFDataObjectOption::IsRequired => true))
		 *     );
		 *   }
		 * }
		 * </code>
		 */
		final protected function addColumns($cols)
		{
			// can pass in an array of CDFDataColumn objects or use variable args
			if(!is_array($cols))
				$cols = func_get_args();

			if($this->_columns === null)
				$this->_columns = array();

			foreach($cols as $col)
			{
				// skip any non objects that may be in the array
				if(!($col instanceof CDFDataColumn))
					continue;

				$this->_columns[] = $col;
			}
		}

		/**
		 * Retrieves the defined name for a table in a data store that this DataObject represents.
		 * @return null|string
		 */
		final public function getTableName()
		{
			return $this->_tableName;
		}

		/**
		 * Sets the name of a table in a data store that this DataObject represents.
		 * @param string $name
		 * @return void
		 */
		final public function setTableName($name)
		{
			$this->_tableName = $name;
		}

		/**
		 * Finds a column by name in the list of columns. Null if not found.
		 * @param string $key
		 * @return CDFDataColumn|null
		 */
		private function findColumn($key)
		{
			if($this->_columns === null || $key == null)
				return null;

			foreach($this->_columns as $col)
				if($col->getName() == $key)
					return $col;

			return null;
		}

		//
		// Value setters
		//

		/**
		 * Sets a value of a column to be a string, passing it to AsStringSafe first. Use with String and Text fields.
		 * @param string $key
		 * @param mixed $value
		 * @param bool $stripHTML If true, string will have HTML/XML removed; false to preserve.
		 * @param bool $allowNull If false, string will be set to '' if value is null; true will allow null will be set.
		 */
		final protected function setColumnString($key, $value, $stripHTML = true, $allowNull = false)
		{
			$col = $this->findColumn($key);
			if($col === null)
				return;

			$col->setValue($allowNull && is_null($value) ? null : CDFDataHelper::AsStringSafe($value, $stripHTML));
		}

		/**
		 * Sets a value of a column to be an integer.
		 * @param string $key
		 * @param mixed $value
		 * @param bool $allowNull If false, integer will be 0 if value is null; true will allow null will be set.
		 */
		final protected function setColumnInteger($key, $value, $allowNull = false)
		{
			$col = $this->findColumn($key);
			if($col === null)
				return;

			$col->setValue($allowNull && is_null($value) ? null : CDFDataHelper::AsInt($value));
		}

		/**
		 * Sets a value of a column to be a float.
		 * @param string $key
		 * @param mixed $value
		 * @param bool $allowNull If false, float will be 0 if value is null; true will allow null will be set.
		 */
		final protected function setColumnFloat($key, $value, $allowNull = false)
		{
			$col = $this->findColumn($key);
			if($col === null)
				return;

			$col->setValue($allowNull && is_null($value) ? null : CDFDataHelper::AsFloat($value));
		}

		/**
		 * Sets a value of a column to be true or false.
		 * @param string $key
		 * @param mixed $value
		 * @param bool $allowNull If false, boolean will be false if value is null; true will allow null will be set.
		 * @return void
		 */
		final protected function setColumnBoolean($key, $value, $allowNull = false)
		{
			$col = $this->findColumn($key);
			if($col === null)
				return;

			$col->setValue($allowNull && is_null($value) ? null : CDFDataHelper::AsBool($value));
		}

		/**
		 * Sets a value of a column to be a DateTime.
		 * @param string $key
		 * @param mixed $value
		 * @param bool $allowNull If false, date will be the epoch start date (1-Jan-1970) if value is null; true will allow null will be set.
		 * @return void
		 */
		final protected function setColumnDateTime($key, $value, $allowNull = false)
		{
			$col = $this->findColumn($key);
			// only allow this on defined Timestamp columns
			if($col === null || $col->getDataType() !== CDFSqlDataType::Timestamp)
				return;

			$col->setValue($allowNull && is_null($value) ? null : $value); // forces to use CDFDataHelper::AsDateTime
		}

		/**
		 * Sets a binary value of a column.
		 * @param string $key
		 * @param string $value
		 * @param bool $allowNull If false, integer will be 0 if value is null; true will allow null will be set.
		 * @return void
		 */
		final protected function setColumnData($key, $value, $allowNull = false)
		{
			$col = $this->findColumn($key);
			// only allow on binary data types
			if($col === null || $col->getDataType() !== CDFSqlDataType::Data)
				return;

			$col->setValue($allowNull && is_null($value) ? null : $value);
		}

		//
		// Value getters
		//

		/**
		 * Wrapper for retrieving a value from a column.
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return bool|DateTime|float|int|string|null
		 */
		private function getColumnValue($key, $allowNull = false)
		{
			$col = $this->findColumn($key);
			if($col === null)
				throw new CDFColumnDataException($key, 'Column does not exist');

			// don't return null if not specified, based on column type
			$value = $col->getValue();
			if($value === null && $allowNull == false)
			{
				switch($col->getDataType())
				{
					case CDFSqlDataType::String:
					case CDFSqlDataType::Text:
					case CDFSqlDataType::Data:
						$value = '';
						break;
					case CDFSqlDataType::Float;
						$value = 0.0;
						break;
					case CDFSqlDataType::Integer;
						$value = 0;
						break;
					case CDFSqlDataType::Bool;
						$value = false;
						break;
					case CDFSqlDataType::Timestamp;
						$value = CDFDataHelper::AsDateTime(null);
						break;
				}
			}
			return $value;
		}

		/**
		 * Gets the value of the column as a string. Also use for Data columns.
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return string
		 */
		final protected function getColumnString($key, $allowNull = false)
		{
			$value = $this->getColumnValue($key, $allowNull);
			if(!is_null($value) && !is_string($value))
				throw new CDFColumnDataException($key, 'Column does not contain string data');
			return $value;
		}

		/**
		 * Gets the value of an integer column.
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return int
		 */
		final protected function getColumnInteger($key, $allowNull = false)
		{
			$value = $this->getColumnValue($key, $allowNull);
			if(!is_null($value) && !is_int($value))
				throw new CDFColumnDataException($key, 'Column is not an integer');
			return $value;
		}

		/**
		 * Gets the value of a float column.
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return float
		 */
		final protected function getColumnFloat($key, $allowNull = false)
		{
			$value = $this->getColumnValue($key, $allowNull);
			if(!is_null($value) && !is_float($value))
				throw new CDFColumnDataException($key, 'Column is not a float');
			return $value;
		}

		/**
		 * Gets the value of a boolean column.
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return bool
		 */
		final protected function getColumnBool($key, $allowNull = false)
		{
			$value = $this->getColumnValue($key, $allowNull);
			if(!is_null($value) && !is_bool($value))
				throw new CDFColumnDataException($key, 'Column is not a boolean');
			return $value;
		}

		/**
		 * Gets the value of a timestamp column
		 * @throws CDFColumnDataException
		 * @param string $key
		 * @param bool $allowNull
		 * @return DateTime
		 */
		final protected function getColumnDateTime($key, $allowNull = false)
		{
			$value = $this->getColumnValue($key, $allowNull);
			// "null" times are set to midnight Jan 1 1970 (epoch) when the column is changed, but existing data may be actually "null"
			if(!is_null($value) && !($value instanceof DateTime))
				throw new CDFColumnDataException($key, 'Column is not a timestamp');
			return $value;
		}

		//
		// Data query utility
		//

		/**
		 * Adds columns to the current data store query.
		 * Never adds any column called 'Id'.
		 * Note that column names will want to match those as in the database table otherwise this won't work as expected.
		 *
		 * @param CDFIDataConnection $db An open database connection
		 * @param array $keys List of column keys. Behaviour affected by $include. If null, adds all.
		 * @param $include bool If true, ONLY columns in $keys will be added. If false, columns in $keys will be SKIPPED.
		 * @return int Number of columns added to the query.
		 */
		final protected function addColumnsToParameters(CDFIDataConnection $db, $keys = null, $include = false)
		{
			if($this->_columns == null || $db == null)
				return 0;

			$num = 0;
			foreach($this->_columns as $col)
			{
				if(strcasecmp($col->getName(), self::IgnoreColumnIdentity) == 0)
					continue; // skip identity column
				if($keys != null)
				{
					$search = array_search($col->getName(), $keys);
					if($include == false && $search !== false)
						continue; // skip column if excluding (default)
					if($include == true && $search === false)
						continue; // skip column if including
				}
				$db->AddParameter($col->getDataType(), $col->getValue());
				$num++;
			}

			return $num;
		}

		/**
		 * Loads values from an array, returned by, for example, mysql_fetch_assoc.
		 * @param array $rows
		 * @throws CDFColumnDataException
		 * @return bool True on success
		 */
		final protected function loadColumnValues($rows)
		{
			if($rows === false || !is_array($rows) || count($rows) == 0)
				return false;

			foreach($this->_columns as $col)
			{
				// find the value in the input array
				if(!isset($rows[$col->getName()]))
					continue;
				// change the value. throws CDFColumnDataException if incompatible
				$col->setValue($rows[$col->getName()]);
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
		 * @throws CDFInvalidOperationException
		 */
		private function addValidationError($key, $code, $msg = null)
		{
			if($this->_validationErrors === null)
				throw new CDFInvalidOperationException('Initial validation not performed first');
			$err = new CDFDataObjectValidationError($key, $code);
			if($msg !== null)
				$err->setErrorDescription($msg);
			$this->_validationErrors[] = $err;
		}

		/**
		 * @param CDFDataColumn $col
		 * @param float $number
		 * @param bool $stopOnError
		 * @return bool
		 */
		private function testValidationNumberRange($col, $number, $stopOnError)
		{
			if($col->getMinRange() == 0 && $col->getMaxRange() == 0)
				return true; // not defined
			
			$value = $col->getMaxRange();
			if($value != 0 && $number > $value)
			{
				$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueRangeTooFar);
				if($stopOnError)
					return false;
			}

			// min range
			$value = $col->getMinRange();
			if($number < $value)
			{
				$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueRangeTooNear);
				if($stopOnError)
					return false;
			}

			return true;
		}

		/**
		 * Performs validation on the values of the current columns.
		 * The validation to perform on each column is defined by the options associated with the column.
		 * This returns an array of CDFDataObjectValidationError classes for any errors found.
		 * Optionally specify an array of columns to check, otherwise it checks all columns.
		 * Note that any column explicitly called 'Id' is always skipped.
		 *
		 * @param array $cols Column keys to check or null to check all.
		 * @param bool $stopOnError If true, returns immediately when one validation check fails.
		 * @throws CDFInvalidOperationException
		 * @return bool True if an error occurred
		 */
		final public function doValidation($cols = null, $stopOnError = false)
		{
			$this->_validationErrors = array();
			if($this->_columns == null)
				throw new CDFInvalidOperationException('No columns to validate');

			foreach($this->_columns as $col)
			{
				if(strcasecmp($col->getName(), self::IgnoreColumnIdentity) == 0) // always skip database Id fields
					continue;
				if($cols != null && !isset($cols[$col->getName()])) // skip?
					continue;

				// is null
				if($col->getIsNotNull())
				{
					if($col->getValue() === null)
					{
						// column is null, shouldn't be
						$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueCannotBeNull);
						if($stopOnError == true)
							break;

						// prevent validation for this column continuing if it shouldn't be null
						continue;
					}
				}

				$type = $col->getDataType();

				// is required
				if($col->getIsRequired())
				{
					$fail = false;
					switch($type)
					{
						case CDFSqlDataType::String:
						case CDFSqlDataType::Text:
							$fail = strlen($col->getValue()) == 0;
							break;
						case CDFSqlDataType::Timestamp:
							// if epoch, not specified
							$fail = CDFDataHelper::AsDateTime($col->getValue())->getTimestamp() == 0;
							break;
					}

					if($fail != false)
					{
						$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueIsNotSet);
						if($stopOnError == true)
							break;
					}
				}

				// string operations
				if($type == CDFSqlDataType::String || $type == CDFSqlDataType::Text || $type == CDFSqlDataType::Data)
				{
					// max length
					$value = $col->getMaxLength();
					if($value > 0 && strlen($col->getValue()) > $value)
					{
						$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueLengthTooLong);
						if($stopOnError == true)
							break;
					}

					// min length
					$value = $col->getMinLength();
					$len = strlen($col->getValue());
					if($value > 0 && $len < $value && $len !== 0)
					{
						$this->addValidationError($col->getName(), CDFValidationErrorCode::ValueLengthTooShort);
						if($stopOnError == true)
							break;
					}
				}

				// number operations
				if($type == CDFSqlDataType::Integer || $type == CDFSqlDataType::Float)
				{
					if(!$this->testValidationNumberRange($col, $col->getValue(), $stopOnError))
						break;
				}

				// time operations
				if($type == CDFSqlDataType::Timestamp)
				{
					/** @var $ts DateTime */
					$ts = $col->getValue();
					if(!$this->testValidationNumberRange($col, $ts == null ? 0 : $ts->getTimestamp(), $stopOnError))
						break;
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
			return count($this->_validationErrors) > 0 ? true : false;
		}

		/**
		 * Returns a list of errors since the last call to doValidation.
		 * @return CDFDataObjectValidationError[] Zero or more CDFDataObjectValidationError's
		 */
		final public function getValidationErrors()
		{
			return $this->_validationErrors;
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
		 * @param string[]|null $skipKeys Columns to skip, or null to add all.
		 * @param bool $skipIdentity If true, will skip 'Id' field.
		 * @param bool $applyTicks If true, wraps column names in SQL back ticks.
		 * @return string[] List of columns.
		 */
		private function getColumnNames($skipKeys = null, $skipIdentity = true, $applyTicks = true)
		{
			$cols = array();
			foreach($this->_columns as $col)
			{
				// Always skip 'Id' column, if told
				if(($skipIdentity && strcasecmp($col->getName(), self::IgnoreColumnIdentity) == 0) ||
				   ($skipKeys != null && array_search($col->getName(), $skipKeys) !== false))
					continue; // skip if specified in keys array
				if($applyTicks)
					$cols[] = sprintf('`%s`', $col->getName()); // surround in back ticks for safety
				else
					$cols[] = $col->getName();
			}
			return $cols;
		}

		private function requireTableName($tableName)
		{
			if($tableName == null)
			{
				if($this->_tableName == null)
					throw new CDFInvalidArgumentException('Table name not set');

				$tableName = $this->_tableName;
			}

			return $tableName;
		}

		/**
		 * Performs an INSERT INTO query on the supplied table using the parameters added to the query.
		 * <code>
		 *   $foo->addColumns(...);
		 *   $foo->setTableName('table');
		 *   $foo->setColumnString('foo', 'abc');
		 *   $foo->setColumnInteger('bar', 12345);
		 *   $foo->addColumnsToParameters($db); // where db is an open data connection
		 *   $foo->queryInsertInto($db);
		 * </code>
		 * This example would execute the following SQL:
		 *   insert into `table` (`foo`,`bar`) values ('abc',12345)
		 *
		 * @param CDFIDataConnection $db Open database connection
		 * @param string|null $tableName Name of the table to use, if different than defined for the object.
		 * @param string[]|null $skipKeys List of columns to skip adding to the query.
		 * @return void
		 */
		final protected function queryInsertInto(CDFIDataConnection $db, $tableName = null, $skipKeys = null)
		{
			// get the table name
			$tableName = $this->requireTableName($tableName);

			// get the column names
			$cols = $this->getColumnNames($skipKeys);

			// create the sql. something like this:
			// insert into `table` (`foo`,`bar`) values (?,?)
			// query processor would replace ? with actual values, supplied by a previous call to addColumnsToParameters
			$values = substr(str_repeat(CDFIDataConnection_TokenCharacter . ',', count($cols)), 0, -1); // trim off last comma
			$sql = sprintf('insert into `%s` (%s) values (%s)', $tableName, implode(',', $cols), $values);

			// pass the query to the processor
			$db->Query($sql);
		}

		/**
		 * Performs an UPDATE query on the supplied table, using the current columns.
		 * @param CDFIDataConnection $db Database connection
		 * @param string $tableName Name of the table to use, if different than defined for the object.
		 * @param string $whereColumn Column that should be used as a requisite for the query (where)
		 * @param string[]|null $skipKeys List of columns to skip
		 * @return void
		 */
		final protected function queryUpdate(CDFIDataConnection $db, $tableName = null, $whereColumn = null, $skipKeys = null)
		{
			// note: whereColumn can be null - be warned!

			// get the table name
			$tableName = $this->requireTableName($tableName);

			// build list of setters
			$sets = array();
			foreach($this->getColumnNames($skipKeys) as $key)
				$sets[] = $key . '=' . CDFIDataConnection_TokenCharacter; // foo=?

			// build sql
			$sql = sprintf('update `%s` set %s ', $tableName, implode(',', $sets));

			// append an additional parameter for the where clause
			$where = $this->findColumn($whereColumn);
			if($where != null)
			{
				$db->AddParameter($where->getDataType(), $where->getValue());
				$sql .= sprintf(' where `%s`=', $where->getName()) . CDFIDataConnection_TokenCharacter;
			}

			// pass query on to processor
			$db->Query($sql);
		}

		/* These special key names allow for the where clauses to determine whether or not each clause is grouped by AND or OR.
		 * For example:
		 *
		 *  - array('ColA' => 'Apple', 'ColB' => 'Pear', 'ColC' => 'Banana')
		 * => (ColA = 'Apple' OR ColB = 'Pear' OR ColC = 'Banana')
		 *
		 *  - array(
		 *     CDFWhereClauseComparisonKey => CDFWhereClauseComparisonAND,
		 *     'ColA' => 'Apple', 'ColB' => 'Pear', 'ColC' => 'Banana')
		 * => (ColA = 'Apple' AND ColB = 'Pear' AND ColC = 'Banana')
		 *
		 * TODO: Make this allow for multiple comparison keys in a single clause array (to switch between AND and OR, with correct use of braces to separate clauses).
		 */

		/**
		 * Defines the comparison to use in a where clause.
		 */
		const CDFWhereClauseComparisonKey = '!CDFWhere';
		/**
		 * Defines to use an 'AND' comparison in a where clause.
		 */
		const CDFWhereClauseComparisonAND = '!AND';
		/**
		 * Defines to use an 'OR' comparison in a where clause. This is default.
		 */
		const CDFWhereClauseComparisonOR = '!OR';

		// This supports the following scenarios:
		// array('column' => 'value', 'column2' => 'value2')
		// array('column', 'value', 'column', 'value2') // note same column key
		private function addWhereClauses(CDFIDataConnection $db, $whereClauses, $tableName)
		{
			$sql = '';

			if($whereClauses != null && is_array($whereClauses))
			{
				// group all the possible values for each key
				$whereValues = array(); // $whereValues[$key] = array($values...)
				reset($whereClauses);
				for(;;)
				{
					$whereValue = current($whereClauses);
					if($whereValue === false)
						break;

					// find the key
					$whereKey = key($whereClauses);
					if(is_int($whereKey))
					{
						// not using a keyed array
						$whereKey = $whereValue;
						next($whereClauses);
						$whereValue = current($whereClauses);
						if($whereValue === false)
							throw new CDFInvalidArgumentException(sprintf('Missing value for key %s', $whereKey));
					}

					// add this value under the key
					$whereValues[$whereKey][] = $whereValue;

					next($whereClauses);
				}

				// add to query
				if(count($whereValues) > 0)
				{
					$sql .= ' where ';
					$sqlFragments = array();
					// default to use 'or'
					$fragmentSeparator = ' or ';
					foreach($whereValues as $key => $values)
					{
						if($key == self::CDFWhereClauseComparisonKey)
						{
							if(count($values) == 1)
							{
								switch($values[0])
								{
									case self::CDFWhereClauseComparisonAND:
										$fragmentSeparator = ' and ';
										break;
									case self::CDFWhereClauseComparisonOR:
										$fragmentSeparator = ' or ';
										break;
									default:
										throw new CDFInvalidOperationException('Unsupported where clause comparison');
								}
							}
							else
								throw new CDFInvalidOperationException('Where clause comparison value not set');
							continue;
						}

						// find out what type the column is
						$col = $this->findColumn($key);
						$type = CDFSqlDataType::String; // blindly assume it will be a string
						if($col == null)
						{
							// if we're searching our own table, then clearly this is an error
							if($tableName == $this->_tableName)
								throw new CDFInvalidArgumentException('Invalid where key');

							// if not our own table, just carry on, default to string type
						}
						else
							$type = $col->getDataType();

						// if more than one value for this key, do an 'or' query
						if(count($values) > 1)
						{
							$valueList = array();
							foreach($values as $value)
							{
								if(is_null($value))
									$valueList[] = sprintf('`%s` is NULL', $key);
								else
								{
									$db->AddParameter($type, $value);
									$valueList[] = sprintf('`%s`=?', $key);
								}
							}
							$sqlFragments[] = sprintf('(%s)', implode($fragmentSeparator, $valueList));
						}
						elseif(count($values) == 1)
						{
							// single value
							$value = $values[0];
							if(is_null($value))
								$sqlFragments[] = sprintf('`%s` is NULL', $key);
							else
							{
								$db->AddParameter($type, $value);
								$sqlFragments[] = sprintf('`%s`=?', $key);
							}
						}
					}
					// join all the fragments together with 'and'
					$sql .= implode(' and ', $sqlFragments);
				}
			}

			return $sql;
		}

		/**
		 * Performs a SELECT query on the table, with the specified where clauses.
		 * @param CDFIDataConnection $db
		 * @param string[]|null $whereClauses List of keyed strings (['column']=>'value')
		 * @param bool[]|null $orderClauses List of columns to order by. True for ascended, false for descending.
		 * @param string|null $tableName Name of the table to use, if different than defined for the object.
		 * @param string[]|null $skipKeys List of columns to skip. If null, will query for all columns (*)
		 * @return array
		 */
		final protected function querySelect(CDFIDataConnection $db, $whereClauses = null, $orderClauses = null, $tableName = null, $skipKeys = null)
		{
			// note: this is only intended to do simple queries

			// get table name
			$tableName = $this->requireTableName($tableName);

			// all queries start with a select
			$sql = 'select ';

			// skipping columns?
			if($skipKeys != null && is_array($skipKeys))
			{
				// join column list together, include identity field
				$sql .= implode(',', $this->getColumnNames($skipKeys, false));
			}
			else
				$sql .= '*'; // all columns

			// append table name
			$sql .= sprintf(' from `%s`', $tableName);

			// where clauses?
			$sql .= $this->addWhereClauses($db, $whereClauses, $tableName);

			// order by?
			if($orderClauses != null && is_array($orderClauses))
			{
				$orders = array();
				// build list of columns and their sort orders
				foreach($orderClauses as $orderKey => $orderAscended)
					$orders[] = sprintf('`%s` %s', $orderKey, $orderAscended ? 'ASC' : 'DESC');

				// add to query
				if(count($orders) > 0)
					$sql .= ' order by ' . implode(', ', $orders);
			}

			// execute query
			return $db->Query($sql);
		}

		/**
		 * Performs a DELETE FROM query on the table, with the specified where clauses.
		 * @param CDFIDataConnection $db
		 * @param string[]|null $whereClauses List of keyed strings (['column']=>'value')
		 * @param string|null $tableName Name of the table to use, if different than defined for the object.
		 * @return array
		 */
		final protected function queryDelete(CDFIDataConnection $db, $whereClauses = null, $tableName = null)
		{
			// note: whereClauses can be null - be warned!

			// get table
			$tableName = $this->requireTableName($tableName);

			// do the query
			return $db->Query(sprintf('delete from `%s`%s', $tableName, $this->addWhereClauses($db, $whereClauses, $tableName)));
		}

		/**
		 * Returns an array containing all the defined column names for this data object.
		 * Intended usage for this is to include in a custom SQL SELECT query.
		 * @param bool $useTicks If true, will wrap column names around back tick characters (escaped for SQL).
		 * @return string[]
		 */
		final public function getAllColumnNames($useTicks = true)
		{
			return $this->getColumnNames(null, false, $useTicks);
		}
	}
