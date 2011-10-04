<?php
/**
 * @author     James Moran
 * @since      Sep, 28 2011
 * @package    JM_CSV
 * @copyright  Copyright (c) 2011 James Moran
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 */

class JM_CSV_Parser
{
	/**
	 * @var file
	 */
	protected $_file = NULL;
	
	/**
	 * @var array
	 */
	protected $_header;

	/**
	 * @var array
	 */
	protected $_map = array();

	/**
	 * @var array
	 */
	protected $_whitelist = NULL;

	/**
	 * @var array
	 */
	protected $_blacklist = NULL;

	/**
	 * Generates a stdClass object from a CSV
	 * file.  Nested objects can be achieved
	 * by using a '.' operator in the file
	 * header
	 * 
	 * EXAMPLE
	 * 
	 * user.firstname,user.lastname
	 * 
	 * will result in:
	 *  
	 * user->firstname
	 * user->lastname
	 */
	public function __construct($file_name=NULL)
	{
		if (!is_null($file_name))
		{			
			$this->setFile($file_name);
		}
	}

	/**
	 * sets a mapping array for altering column headers
	 * to another column name, even a '.' seperated
	 * column name to take advantage of nested objects
	 * or arrays
	 *
	 * @param array $map
	 * @return JM_CSV_Parser
	 */
	public function setColumnMap(array $map)
	{
		$this->_map = $map;
		return $this;
	}

	/**
	 * sets a whitelist of columns that will be used
	 * from the csv file
	 *
	 * @param array $list
	 * @return JM_CSV_Parser
	 */
	public function setWhitelist(array $list)
	{
		$this->_whitelist = $list;
		return $this;
	}

	/**
	 * sets a blacklist of columns in the csv file
	 * that will not be used 
	 *
	 * @param array $list
	 * @return JM_CSV_Parser
	 */
	public function setBlacklist(array $list)
	{
		$this->_blacklist = $list;
		return $this;
	}

	/**
	 * sets the filename to be used by the parser;
	 * must be a fully qualified path to the file
	 * 
	 * @param string $file_name
	 * @return JM_CSV_Parser
	 */
	public function setFile($file_name)
	{
		$this->_file   = fopen($file_name, 'r');
		$this->_header = $this->_getHeader($this->_file);
		return $this;
	}

	/**
	 * retrieve the next object from the file
	 * 
	 * @return stdClass $obj
	 */
	public function getNextObject()
	{
		$parsed = $this->getNextArray();
		$obj    = $this->_toObject($parsed);
		
		return $obj;
	}

	/**
	 * retrieve the next object from the file
	 * in an array format
	 *
	 * @return array $parsed
	 */
	public function getNextArray()
	{
		$header = $this->_getMappedHeaders($this->_header);
		if ($this->_file === NULL)
		{
			throw new Exception('No file has been provided');
		}
		
		$row    = $this->_getRow($header);
		$parsed = $this->_parseRow($row);
		
		return $parsed;
	}

	/**
	 * function to transform the multi-dimentional 
	 * array into a nested stdClass object
	 * 
	 * @param array $array
	 * @return stdClass $return
	 */
	protected function _toObject($array) 
	{
		$return = new stdClass();
	
		foreach ($array as $k => $v) 
		{
			if (is_array($v)) 
			{
				$return->$k = $this->_toObject($v);
			}
			else 
			{
				$return->$k = $v;
			}
		}
	 
		return $return;
	}
	
	/**
	 * function to parse the row of data
	 * into a multi-dimentional array
	 * 
	 * @param array $row
	 * @return array $return
	 */
	protected function _parseRow($row)
	{
		$return = array();
		
		foreach ($row as $key=>$value)
		{
			$return = array_merge_recursive(
				$return, 
				$this->_parseCol($key, $value)
			);
		}
		return $return;
	}
	
	/**
	 * parses a single column using a '.' to split
	 * the column into a multi-dimentional array
	 * 
	 * @param string $key
	 * @param string $value
	 * @return array $return
	 */
	protected function _parseCol($key, $value)
	{
		$parts      = explode('.', $key);
		$last_index = count($parts)-1;
		
		$return = array($parts[$last_index--]=>$value);
		
		for ($last_index; $last_index>=0; $last_index--)
		{
			$return[$parts[$last_index]] = $return;
			unset($return[$parts[$last_index+1]]);
		}
		return $return;
	}

	/**
	 * maps headers to those supplied in the map
	 *
	 * @param array $headers
	 * @return array $headers
	 */
	protected function _getMappedHeaders(array $headers)
	{
		foreach ($headers as $key=>$header)
		{
			if (isset($this->_map[$header]))
			{
				$headers[$key] = $this->_map[$header];
			}
		}
		return $headers;
	}

	/**
	 * retrieve the column headers
	 * 
	 * @param file $file
	 * @return array $headers
	 */
	protected function _getHeader($file)
	{
		$headers = fgetcsv($file);
		return $headers;
	}

	/**
	 * determine if the column name is in the whitelist; 
	 * returns TRUE if the whitelist is not set
	 *
	 * @param string $column_name
	 * @return boolean
	 */
	protected function _isWhitelisted($column_name)
	{
		if ($this->_whitelist === NULL || in_array($column_name, $this->_whitelist))
		{
			return TRUE;
		}
		return FALSE;
	}

	/**
	 * method to determine if the column name is on
	 * the blacklist of columns not to use, if one exists
	 * returns FALSE if the blacklist is not set
	 *
	 * @param string $column_name
	 * @return boolean
	 */
	protected function _isBlacklisted($column_name)
	{
		if ($this->_blacklist === NULL || !in_array($column_name, $this->_blacklist))
		{
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * retrieve a row from the file and
	 * returns an array using the column
	 * headers for the key
	 * 
	 * @param array $header
	 * @return array $return
	 */
	protected function _getRow($header)
	{
		$return = array();
		
		if ($row = fgetcsv($this->_file))
		{
			foreach ($row as $key=>$value)
			{
				if ($this->_isWhitelisted($header[$key]) &&
					!$this->_isBlacklisted($header[$key]))
				{
					$return[$header[$key]] = $value;
				}
			}
		}
		return $return;
	}
}
