<?php

class JMToolkit_CSV_Writer
{
	protected $_data;
	protected $_header = NULL;
	protected $_file;

	public function __construct($filepath=NULL)
	{
		if (!is_null($filepath))
		{
			$this->setFile($filepath);
		}
	}

	public function setFile($filepath)
	{
		$this->_file = fopen($filepath, 'w');
	}

	public function addData($data)
	{
		$this->_makeArray($data);
		if ($this->_header === NULL)
		{
			$this->_header = array_keys($this->_data);
			$this->_writeRow($this->_header);
		}
		$this->_writeRow($this->_data);
	}

	public function getCsvFile()
	{
		return $this->_file;
	}

	protected function _makeArray($data, $key=NULL)
	{
		foreach ($data as $k=>$v)
		{
			$cleanKey = is_null($key) ? $k : $key . '.' . $k;
			if (is_array($v) || is_object($v))
			{
				$this->_makeArray($v, $cleanKey);
			}
			else
			{
				$this->_data[$cleanKey] = $v;
			}
		}
	}

	protected function _writeRow($data)
	{
		if (!fputcsv($this->_file, $data))
		{
			throw new Exception('Failure to write to file');
		}
	}
}
