<?php

/**
 * CDFDataColumn and all the implementations define the columns for a CDFDataObject, representing table column structure.
 * @package CDF
 */

require_once 'CDFIDataConnection.php';
require_once dirname(__FILE__) . '/../core/CDFDataHelper.php';
require_once dirname(__FILE__) . '/../core/CDFExceptions.php';

// Please use the constants and not the values directly!
/**
 * Constants to use for specifying options to CDFDataColumns.
 */
interface CDFDataColumnOption
{
	// if true, value is required (strings, timestamp)
	const IsRequired = 'req';
	// if true, value not allowed to be null
	const NotNull = 'nn';
	// maximum length of the value (string, data)
	const MaxLength = 'Ml';
	// minimum length of the value (string, data)
	const MinLength = 'ml';
	// maximum range for the value (int, float, timestamp)
	const MaxRange = 'Mr';
	// minimum range for the value (int, float, timestamp)
	const MinRange = 'mr';
}

/**
 * Defines a column inside a CDFDataObject.
 * @throws CDFInvalidArgumentException
 */
abstract class CDFDataColumn
{
	/** @var int */
	private $_dataType; // value of CDFSqlDataType
	/** @var string */
	private $_name; // column name
	/** @var mixed */
	protected $_value; // column data
	/** @var bool */
	private $_optionNotNull = false; // if true, value not allowed to be null
	/** @var bool */
	private $_optionIsRequired = false; // if true, value is required (strings, timestamp)
	/** @var int */
	private $_optionMaxLength = 0; // maximum length of the value (string, data)
	/** @var int */
	private $_optionMinLength = 0; // minimum length of the value (string, data)
	/** @var float */
	private $_optionMinRange = 0; // minimum range for the value (int, float, timestamp)
	/** @var float */
	private $_optionMaxRange = 0; // maximum range for the value (int, float, timestamp)

	/**
	 * Initialises the column.
	 * @param int $dataType A value from CDFSqlDataType
	 * @param string $name Name of the column
	 * @param mixed|null $value Default value for the column or null.
	 * @param array|null $opts A list of options to set, based on CDFDataColumnOption
	 */
	protected function __construct($dataType, $name, $value = null, $opts = null)
	{
		if(!is_int($dataType) || !is_string($name))
			throw new CDFInvalidArgumentException();

		$this->_dataType = $dataType;
		$this->_name = $name;
		if($value != null)
			$this->setValue($value);
		else
			$this->_value = null;

		$this->parseOptions($opts);
	}

	/**
	 * Sets the value of the column.
	 * @abstract
	 * @param mixed|null $value
	 * @return void
	 * Implementations should do whatever special handling required for the defined data type of the column.
	 */
	abstract public function setValue($value);
	/**
	 * Gets the value of the column.
	 * @abstract
	 * @return mixed|null
	 */
	abstract public function getValue();

	/**
	 * Returns the name of the column.
	 * @return string
	 */
	final public function getName()
	{
		return $this->_name;
	}

	/**
	 * Returns the CDFSqlDataType for this column.
	 * @return int
	 */
	final public function getDataType()
	{
		return $this->_dataType;
	}

	//
	// Options
	//

	/**
	 * Parses options specified for the column.
	 * Use a keyed array to pass options.
	 * <code>
	 * 	$this->parseOptions(array(
	 * 		CDFDataColumnOption::IsRequired => true,
	 * 		CDFDataColumnOption::MaxLength => 250
	 * 	));
	 * </code>
	 * @param array $opts
	 * @return void
	 */
	final protected function parseOptions($opts)
	{
		if(is_null($opts) || !is_array($opts))
			return;

		foreach($opts as $key => $value)
		{
			switch($key)
			{
				case CDFDataColumnOption::IsRequired:
					$this->_optionIsRequired = $value;
					break;
				case CDFDataColumnOption::MaxLength:
					$this->_optionMaxLength = $value;
					break;
				case CDFDataColumnOption::MaxRange:
					$this->_optionMaxRange = $value;
					break;
				case CDFDataColumnOption::MinLength:
					$this->_optionMinLength = $value;
					break;
				case CDFDataColumnOption::MinRange:
					$this->_optionMinRange = $value;
					break;
				case CDFDataColumnOption::NotNull:
					$this->_optionNotNull = $value;
					break;
			}
		}
	}

	/**
	 * @return boolean
	 */
	final public function getIsRequired()
	{
		return $this->_optionIsRequired;
	}

	/**
	 * @return int
	 */
	final public function getMaxLength()
	{
		return $this->_optionMaxLength;
	}

	/**
	 * @return float
	 */
	final public function getMaxRange()
	{
		return $this->_optionMaxRange;
	}

	/**
	 * @return int
	 */
	final public function getMinLength()
	{
		return $this->_optionMinLength;
	}

	/**
	 * @return float
	 */
	final public function getMinRange()
	{
		return $this->_optionMinRange;
	}

	/**
	 * @return boolean
	 */
	final public function getIsNotNull()
	{
		return $this->_optionNotNull;
	}
}

//
// Implementations
//

/**
 * Base class for all string-related column types (string, text, data)
 * @throws CDFColumnDataException
 */
abstract class CDFDataColumnStringBase extends CDFDataColumn
{
	/**
	 * @param string|null $value
	 * @return void
	 */
	final public function setValue($value)
	{
		if(!is_null($value) && !is_string($value))
			throw new CDFColumnDataException($this->getName(), 'Value is not string');

		$this->_value = $value;
	}

	/**
	 * Gets the string value of the column.
	 * @return string
	 */
	public function getValue()
	{
		return $this->_value;
	}
}

/**
 * Defines the String data column. (VARCHAR, etc)
 */
final class CDFDataColumnString extends CDFDataColumnStringBase
{
	/**
	 * @param string $name
	 * @param string|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::String, $name, $value, $opts);
	}
}

/**
 * Defines a Text data column. (TEXT, etc)
 */
final class CDFDataColumnText extends CDFDataColumnStringBase
{
	/**
	 * @param string $name
	 * @param string|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Text, $name, $value);
	}
}

/**
 * Defines a Data column. (BLOB, BINARY, etc)
 */
final class CDFDataColumnData extends CDFDataColumnStringBase
{
	/**
	 * @param string $name
	 * @param string|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Data, $name, $value);
	}
}

/**
 * Defines an Integer column. (INT, etc)
 * @throws CDFColumnDataException
 */
final class CDFDataColumnInteger extends CDFDataColumn
{
	/**
	 * @param string $name
	 * @param int|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Integer, $name, $value);
	}

	/**
	 * @param int|null $value
	 * @return void
	 * @throws CDFColumnDataException
	 */
	public function setValue($value)
	{
		if(!is_null($value) && !is_int($value))
			throw new CDFColumnDataException($this->getName(), 'Column is not integer');

		$this->_value = $value;
	}

	/**
	 * Gets the integer value of the column.
	 * @return int|null
	 */
	public function getValue()
	{
		return $this->_value;
	}
}

/**
 * Defines a Float column. (FLOAT, DOUBLE, etc)
 * @throws CDFColumnDataException
 */
final class CDFDataColumnFloat extends CDFDataColumn
{
	/**
	 * @param string $name
	 * @param float|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Float, $name, $value);
	}

	/**
	 * @param float|null $value
	 * @return void
	 * @throws CDFColumnDataException
	 */
	public function setValue($value)
	{
		if(!is_null($value) && !is_float($value))
			throw new CDFColumnDataException($this->getName(), 'Column is not float');

		$this->_value = $value;
	}

	/**
	 * Gets the float value of the column.
	 * @return float|null
	 */
	public function getValue()
	{
		return $this->_value;
	}
}

/**
 * Defines a Timestamp column. (DATETIME, TIMESTAMP, etc)
 */
final class CDFDataColumnTimestamp extends CDFDataColumn
{
	/**
	 * @param string $name
	 * @param DateTime|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Timestamp, $name, $value);
	}
	/**
	 * @param int|DateTime|null $value
	 * @return void
	 */
	public function setValue($value)
	{
		$this->_value = CDFDataHelper::AsDateTime($value);
	}

	/**
	 * Gets the value of the column.
	 * @return DateTime
	 */
	public function getValue()
	{
		return $this->_value;
	}
}

/**
 * Defines a Bool column. (BIT, TINYINT, etc)
 * @throws CDFColumnDataException
 */
final class CDFDataColumnBool extends CDFDataColumn
{
	/**
	 * @param string $name
	 * @param bool|null $value
	 * @param array|null $opts
	 */
	public function __construct($name, $value = null, $opts = null)
	{
		parent::__construct(CDFSqlDataType::Bool, $name, $value);
	}

	/**
	 * @param bool|null $value
	 * @return void
	 * @throws CDFColumnDataException
	 */
	public function setValue($value)
	{
		if(!is_null($value) && !is_bool($value))
			throw new CDFColumnDataException($this->getName(), 'Column is not boolean');

		$this->_value = $value;
	}

	/**
	 * Gets the boolean value of the column.
	 * @return bool|null
	 */
	public function getValue()
	{
		return $this->_value;
	}
}
