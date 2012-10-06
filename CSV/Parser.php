<?php
/**
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
 * @author     James Moran
 * @since      Sep, 28 2011
 * @package    JM_CSV
 * @copyright  Copyright (c) 2011 James Moran
 *
 * < Description >
 *
 * Parses a CSV file into a complex object structure using the first row of the
 * file to define keys using a dot (.) seporated name spacing
 * 
 * EXAMPLE
 * 
 * user.firstname, user.lastname, user.email.work, user.email.home
 * John, Doe, work@email.com, home@email.com
 * 
 * 
 *  
 * user->firstname   == "John"
 * user->lastname    == "Doe"
 * user->email->work == "work@email.com"
 * user->email->home == "home@email.com"
 *
 */

class JMToolkit_CSV_Parser
{
	/**
	 * _options 
	 * 
	 * @var array
	 * @access protected
	 */
	protected $_options = array();

	/**
	 * _file
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_file = NULL;
	
	/**
	 * _num_cols 
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_num_cols;

	/**
	 * _rows_fetched 
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_rows_fetched;

	/**
	 * _setter 
	 * 
	 * @var array
	 * @access protected
	 */
	protected $_setter = array();

	/**
	 * _array 
	 * 
	 * @var array
	 * @access protected
	 */
	protected $_array = array();

	/**
	 * __construct
	 *
	 * @author James Moran
	 * @param mixed $file_name
	 * @param array $options
	 * @access public
	 * @return void
	 */
	public function __construct($file_name=NULL, $options=array())
	{
		if (!is_null($file_name))
		{			
			$this->setFile($file_name);
		}
		$this->_options = array_merge(
			array(
				'whitelist'=>null,
				'blacklist'=>null,
				'column_map'=>null,
			), $options
		);
		$this->_num_cols     = 0;
		$this->_rows_fetched = 0;
	}

	/**
	 * setColumnMap
	 *
	 * sets a mapping array for altering column headers to another column name, 
	 * even a '.' seperated column name to take advantage of nested objects or 
	 * arrays
	 *
 	 * @author James Moran
	 * @param array $map
	 * @access public
	 * @return JMToolkit_CSV_Parser
	 */
	public function setColumnMap(array $map) {
		if ($this->_rows_fetched > 0) {
			throw new JMToolkit_Exception("Attempting to set map after parsing has start");
		}
		$this->_options['column_map'] = $map;
		return $this;
	}

	/**
	 * setWhitelist
	 *
	 * sets a whitelist of columns that will be used from the csv file
	 *
	 * @author James Moran
	 * @param array $list
	 * @access public
	 * @return JMToolkit_CSV_Parser
	 */
	public function setWhitelist(array $list)
	{
		if ($this->_rows_fetched > 0) {
			throw new JMToolkit_Exception("Attempting to set list after parsing has start");
		}
		$this->_options['whitelist'] = $list;
		return $this;
	}

	/**
	 * setBlacklist
	 *
	 * sets a blacklist of columns in the csv file that will not be used 
	 *
	 * @author James Moran
	 * @param array $list
	 * @access public
	 * @return JMToolkit_CSV_Parser
	 */
	public function setBlacklist(array $list)
	{
		if ($this->_rows_fetched > 0) {
			throw new JMToolkit_Exception("Attempting to set list after parsing has start");
		}
		$this->_options['blacklist'] = $list;
		return $this;
	}

	/**
	 * setFile
	 *
	 * sets the file resource using the path
	 *
	 * @author James Moran
	 * @param mixed $path
	 * @access public
	 * @return JMToolkit_CSV_Parser
	 */
	public function setFile($path)
	{
		if (!is_string($path)) {
			throw new JMToolkit_Exception("Path must be a string.");
		}

		$this->_file = $this->__fopen($path, 'r');
		$this->_rows_fetched = 0;
		return $this;
	}

	/**
	 * getNextObject
	 *
	 * retrieve the next row as and object
	 *
	 * @author James Moran
	 * @access public
	 * @return boolean
	 */
	public function getNextObject()
	{
		$array = $this->getNextArray();
		
		return $array ? json_decode(json_encode($array)) : FALSE;
	}

	/**
	 * getNextArray
	 *
	 * retrieve the row in an array format
	 *
	 * @author James Moran
	 * @access public
	 * @return array
	 */
	public function getNextArray()
	{
		$this->_clean();

		if ($this->_rows_fetched == 0) {
			$this->_initArray($this->_getHeader());
		}	

		$return = $this->_getRow();

		if (!empty($return)) {
			$this->_rows_fetched++;
		}	

		return $return;
	}

	/**
	 * __fopen
	 *
	 * @author James Moran
	 * @param mixed $path
	 * @param mixed $options
	 * @access protected
	 * @return file
	 */
	protected function __fopen($path, $options) {
		if (!file_exists($path)) {
			throw new JMToolkit_Exception('File does not exist.');
		}
		return fopen($path, $options);
	}

	/**
	 * _clean 
	 * 
	 * @author James Moran
	 * @access protected
	 * @return void
	 */
	protected function _clean() {
		foreach ($this->_setter as &$field) {
			$field = null;
		}
	}

	/**
	 * _getRow 
	 * 
	 * @author James Moran
	 * @access protected
	 * @return array
	 */
	protected function _getRow() {
		foreach ($this->_readFileRow() as $i=>$value) {
			$this->_setter[$i] = $value;
		}
		return $this->_array;
	}

	/**
	 * _getMappedHeaders
	 *
	 * maps headers to those supplied in the map
	 *
	 * @author James Moran
	 * @param array $headers
	 * @access protected
	 * @return array
	 */
	protected function _getMappedHeaders(array $headers) {
		if (is_array($this->_options['column_map'])) {
			foreach ($headers as $i=>$header) {
				if (isset($this->_options['column_map'])) {
					$headers[$i] = $this->_options['column_map'][$header];
				}
			}
		}
		return $headers;
	}

	/**
	 * _getHeader
	 *
	 * retrieve the column headers
	 * 
	 * @author James Moran
	 * @access protected
	 * @return array
	 */
	protected function _getHeader() {
		$headers = $this->_readFileRow();
		if (empty($headers)) {
			throw new JMToolkit_Exception('File is missing header row');
		}
		$headers = $this->_getMappedHeaders($headers);
		return $headers;
	}

	/**
	 * _removeArray 
	 * 
	 * @author James Moran
	 * @access protected
	 * @return void
	 */
	protected function _removeArray() {
		unset($this->_array);
		unset($this->_setter);
		$this->_array  = array();
		$this->_setter = array();
	}

	/**
	 * _initArray 
	 * 
	 * @author James Moran
	 * @param mixed $keys 
	 * @access protected
	 * @return void
	 */
	protected function _initArray($keys) {
		$this->_removeArray();	
		foreach ($keys as $column => $key) {
			if ($this->_isWhitelisted($key) && !$this->_isBlacklisted($key)) {

				$pointer =& $this->_array;

				$names      = explode('.', $key);
				$last_index = count($names)-1;

				foreach ($names as $i=>$name) {
					if ($i != $last_index) {
						$pointer = &$pointer[$name];
						continue;
					}
					$this->_setter[$column] =& $pointer[$name];
				}
			}
		}	
	}

	/**
	 * _readFileRow
	 *
	 * read a file from the current file marker 
	 * 
	 * @author James Moran
	 * @access protected
	 * @return array
	 */
	protected function _readFileRow() {

		if (is_null($this->_file)) {
			throw new JMToolkit_Exception('No file has been provided');
		}

		$return = fgetcsv($this->_file);

		$size = count($return);

		if ($size != $this->_num_cols) {
			throw new JMToolkit_Exception(sprintf('Invalid file: Error on row %s',$this->_rows_fetched+1));
		}
		else {
			$this->_num_cols = $size;
		}
		return $return;
	}

	/**
	 * _isWhitelisted
	 *
	 * determine if the column name is in the whitelist; 
	 * returns TRUE if the whitelist is not set
	 *
	 * @author James Moran
	 * @param mixed $column_name
	 * @access protected
	 * @return boolean
	 */
	protected function _isWhitelisted($name)
	{
		return (is_null($this->_options['whitelist']) || in_array($name, $this->_options['whitelist'])); 
	}

	/**
	 * _isBlacklisted
	 *
	 * method to determine if the column name is on
	 * the blacklist of columns not to use, if one exists
	 * returns FALSE if the blacklist is not set
	 *
	 * @author James Moran
	 * @param mixed $column_name
	 * @access protected
	 * @return boolean
	 */
	protected function _isBlacklisted($name)
	{
		return (!is_null($this->_options['blacklist']) && in_array($name, $this->_options['blacklist']));
	}
}
