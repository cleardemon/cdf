<?php

	/**
	 * Enumerations for the various data types existing in database world.
	 * @author demon
	 */
	final class CDFSqlDataType
	{
		/**
		 * String data type, up to a fixed size.
		 * @var int
		 */
		const String = 1;
		/**
		 * Integer data type.
		 * @var int
		 */
		const Integer = 2;
		/**
		 * Floating point number.
		 * @var int
		 */
		const Float = 3;
		/**
		 * Large amount of text (not a fixed size string)
		 * @var int
		 */
		const Text = 4;
		/**
		 * Timestamp, representing date and time in UTC format.
		 * @var int
		 */
		const Timestamp = 5;
		/**
		 * Boolean or bit.  True or false.
		 * @var int
		 */
		const Bool = 6;
		/**
		 * Binary data; blob. Binary-safe string in PHP.
		 * @var int
		 */
		const Data = 7;
	}

	const CDFIDataConnection_TokenCharacter = '?'; // must be one character

	/**
	 * Defines a common interface for manipulating database providers (such as MySQL).
	 * @author demon
	 */
	interface CDFIDataConnection
	{
		/**
		 * Opens a connection to the data store.
		 * @abstract
		 * @return void
		 */
		public function Open();
		/**
		 * Closes the connection.
		 * @abstract
		 * @return void
		 */
		public function Close();
		/**
		 * Returns true an active connection to the data store is present.
		 * @abstract
		 * @return bool
		 */
		public function HasConnection();
		/**
		 * Resets the connection object ready to accept a new query.
		 * @abstract
		 * @return void
		 */
		public function NewQuery();
		/**
		 * Adds a parameter to use in a query or stored procedure call.
		 * @abstract
		 * @param int $type
		 * @param mixed $value
		 * @return void
		 */
		public function AddParameter($type, $value);
		/**
		 * Executes a SQL query.
		 * @abstract
		 * @param string $sql
		 * @param bool $skipParameters If true, do not process ? in queries.
		 * @return array
		 */
		public function Query($sql, $skipParameters = false);
		/**
		 * Executes a stored procedure.
		 * @abstract
		 * @param string $name
		 * @return array
		 */
		public function Procedure($name);
		/**
		 * Returns last auto inserted value generated by previous query
		 * @abstract
		 * @return int
		 */
		public function LastID();
		/**
		 * Returns the number of affected rows by the previous query.
		 * @abstract
		 * @return int
		 */
		public function getAffectedRowCount();
		/**
		 * Escapes the input parameter as per connection rules. This is only useful for when not using parameters.
		 * @abstract
		 * @param string $var
		 * @return string
		 */
		public function escapeVariable($var);
	}

