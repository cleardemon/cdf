<?php

	final class CDFValidationErrorCode
	{
		const Undefined = 0;
		const ColumnNotSpecified = 1;
		const ValueCannotBeNull = 2;
		const ValueIsNotSet = 3;
		const ValueOutOfRange = 4;
		const ValueLengthTooShort = 5;
		const ValueLengthTooLong = 6;
		const CustomError = 666;
	}

	final class CDFDataObjectValidationError
	{
		/**
		 * @var string Column key that is at fault.
		 */
		private $_columnkey;
		/**
		 * @var int Error enum (see CDFValidationErrorCode)
		 */
		private $_errorcode;
		/**
		 * @var string If CustomError, the message why it is in error
		 */
		private $_custommessage;

		function __construct($column, $code)
		{
			$this->_columnkey = $column;
			$this->_errorcode = $code;
			$this->_custommessage = null;
		}

		/**
		 * @return string Column key at fault.
		 */
		public function getColumnKey()
		{
			return $this->_columnkey;
		}

		/**
		 * @return int Error code - see CDFValidationErrorCode.
		 */
		public function getErrorCode()
		{
			return $this->_errorcode;
		}

		public function setErrorDescription($value)
		{
			$this->_custommessage = $value;
		}

		/**
		 * @return string Human-readable explanation of error.
		 */
		public function getErrorDescription()
		{
			$msg = $this->_custommessage;
			if($msg === null)
			{
				switch($this->_errorcode)
				{
					default:
						$msg = "Undefined validation error.";
						break;
					case CDFValidationErrorCode::ColumnNotSpecified:
						$msg = "The specified column has not been defined.";
						break;
					case CDFValidationErrorCode::ValueCannotBeNull:
						$msg = "Value must be set.";
						break;
					case CDFValidationErrorCode::ValueIsNotSet:
						$msg = "Value has not been specified.";
						break;
					case CDFValidationErrorCode::ValueLengthTooLong:
						$msg = "Value has too many characters.";
						break;
					case CDFValidationErrorCode::ValueLengthTooShort:
						$msg = "Value is too short.";
						break;
					case CDFValidationErrorCode::ValueOutOfRange:
						$msg = "Value is out of the allowed range.";
						break;
				}
			}

			return $msg;
		}
	}
