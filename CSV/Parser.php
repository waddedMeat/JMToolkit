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
	 * sets the filename to be used
	 * must be a fully qualified path to the file
	 * 
	 * @param string $file_name
	 */
	public function setFile($file_name)
	{
			$this->_file   = fopen($file_name, 'r');
			$this->_header = $this->_getHeader($this->_file);
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
		if ($this->_file === NULL)
		{
			throw new Exception('No file has been provided');
		}
		
		$row    = $this->_getRow($this->_header);
		$parsed = $this->_parseRow($row);
		
		return $parsed;
	}
	/**
	 * function to transform the multi-dimentional array
	 * into a nested stdClass object
	 * 
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
	 * @return array $return
	 */
	protected function _parseRow($row)
	{
		$return = array();
		
		foreach ($row as $key=>$value)
		{
			$return = array_merge_recursive($return, $this->_parseCol($key, $value));
		}
		return $return;
	}
	
	/**
	 * parses a single column using a '.' to split
	 * the column into a multi-dimentional array
	 * 
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
	 * retrieve the column headers
	 * 
	 * @return array $header
	 */
	protected function _getHeader($file)
	{
		$header = fgetcsv($file);
		return $header;
	}

	/**
	 * retrieve a row from the file and
	 * returns an array using the column
	 * headers for the key
	 * 
	 * @return array $return
	 */
	protected function _getRow($header)
	{
		$return = array();
		$row    = fgetcsv($this->_file);
		foreach ($row as $key=>$value)
		{
			$return[$header[$key]] = $value;
		}
		return $return;
	}
}
