<?php

require_once '../CSV/Parser.php';
require_once '../Exception.php';

/**
 * ParserTest
 * 
 * @uses PHPUnit
 * @uses _Framework_TestCase
 * @version $id$
 * @copyright 2011 James Moran
 * @author James Moran <moranjk75@gmail.com> 
 */
class ParserTest extends PHPUnit_Framework_TestCase
{
	/**
	 * _csv
	 * 
	 * @var mixed
	 * @access protected
	 */
	protected $_csv;

	/**
	 * setUp
	 *
	 * @access public
	 * @return void
	 */
	public function setUp()
	{
		$this->_csv = <<<CSV
"user.name.first","user.name.last","user.age","user.sex"
CSV;
	}

	/**
	 * testConstructorWithNoOptions
	 *
	 * @access public
	 * @return void
	 */
	public function testConstructorWithNoOptions()
	{
		$parser = $this->getMock('JMToolkit_CSV_Parser');
		$this->assertAttributeEquals(0, '_num_cols', $parser);	
		$this->assertAttributeEquals(0, '_rows_fetched', $parser);	
		$this->assertAttributeEquals(
			array(
				'blacklist'=>null,
				'whitelist'=>null,
				'column_map'=>null
			), '_options', $parser
		);	
	}

	/**
	 * testContructorWithOptions
	 *
	 * @access public
	 * @return void
	 */
	public function testContructorWithOptions()
	{
		$options = array(
			'whitelist'  => array(
				'name1', 
				'name2'
			),
			'blacklist'  => array(
				'name3', 
				'name4'
			),
			'column_map' => array(
				'name1' => 'name2', 
				'name3' => 'name4'
			)
		);
		$parser = $this->getMock(
			'JMToolkit_CSV_Parser',
			array('_openFile'),
			array(
				'filename', 
				$options
			)
		);

		$this->assertAttributeEquals($options, '_options', $parser);
	}

	/**
	 * testSettingFile
	 *
	 * @access public
	 * @return void
	 */
	public function testSettingFile()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$test = uniqid();

		$parser->expects($this->once())
			->method('_openFile')
			->will($this->returnvalue($test));

			$parser->setfile($test);

		$this->assertattributeequals($test, '_file', $parser);

		return $parser;
	}

	/**
	 * testSettingFileResetsTheRowCounter
	 *
	 * @access public
	 * @return void
	 *
	 * @depends testSettingFile
	 */
	public function testSettingFileResetsTheRowsFetchedCounter($parser)
	{
		$test = uniqid();

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$this->assertattributeequals(0, '_rows_fetched', $parser);
		$parser->getNextArray();
		$this->assertattributeequals(1, '_rows_fetched', $parser);

		$parser->setfile($test);
		$this->assertattributeequals(0, '_rows_fetched', $parser);
	}

	/**
	 * testSettingFileWithNonStringPath
	 *
	 * @expectedException JMToolkit_Exception
	 *
	 * @access public
	 * @return void
	 */
	public function testSettingFileWithNonStringPath()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->setfile(array());
	}

	/**
	 * testSetters
	 *
	 * @access public
	 * @return void
	 */
	public function testSetters()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$options = array(
			'whitelist'  => array(
				'name1', 
				'name2'
			),
			'blacklist'  => array(
				'name3', 
				'name4'
			),
			'column_map' => array(
				'name1' => 'name2', 
				'name3' => 'name4'
			)
		);

		$parser->setWhitelist($options['whitelist']);
		$parser->setBlacklist($options['blacklist']);
		$parser->setColumnMap($options['column_map']);

		$this->assertAttributeEquals($options, '_options', $parser);
	}

	/**
	 * testGetNextArrayIncreasesTheRowsFetched
	 *
	 * @access public
	 * @return void
	 */
	public function testGetNextArrayIncreasesTheRowsFetched()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$array = $parser->getNextArray();

		$this->assertAttributeEquals(1, '_rows_fetched', $parser);

		$this->assertNotEmpty($array);	
		$this->assertNotEmpty($array['user']);
		$this->assertEquals(3, count($array['user']));
		$this->assertEquals(2, count($array['user']['name']));
		
		$this->assertEquals("user.name.first",$array['user']['name']['first']);
		$this->assertEquals("user.name.last",$array['user']['name']['last']);
		$this->assertEquals("user.age",$array['user']['age']);
		$this->assertEquals("user.sex",$array['user']['sex']);

		return $parser;
	}

	public function testCleanGetsCalled()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_clean', '_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$parser->expects($this->exactly(2))
			->method('_clean');

		$array = $parser->getNextArray();
		$array = $parser->getNextArray();

		return $parser;
	}

	public function testGettingNextObjectReturnsAnObject()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('getNextArray', '_openFile', '_readFileRow'));

		$parser->expects($this->once())
			->method('getNextArray')
			->will($this->returnValue(array('foo'=>'bar')));

		$obj = $parser->getNextObject();

		$this->assertTrue(property_exists($obj, 'foo'));
		$this->assertEquals('bar', $obj->foo);
	}

	/**
	 * testColumnMap
	 *
	 * @access public
	 * @return void
	 */
	public function testColumnMap()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv('firstname,lastname,age,sex')));

		$map = array(
			'firstname' => 'user.name.first', 
			'lastname'  => 'user.name.last', 
			'age'       => 'user.age', 
			'sex'       => 'user.sex'
		);

		$parser->setColumnMap($map);
		$array = $parser->getNextArray();

		$this->assertNotEmpty($array);	
		$this->assertNotEmpty($array['user']);
		$this->assertEquals(3, count($array['user']));
		$this->assertEquals(2, count($array['user']['name']));

		$this->assertEquals("firstname",$array['user']['name']['first']);
		$this->assertEquals("lastname",$array['user']['name']['last']);
		$this->assertEquals("age",$array['user']['age']);
		$this->assertEquals("sex",$array['user']['sex']);
	}

	public function testBlacklistingColumns()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$list = array('user.name.last', 'user.sex');

		$parser->setBlacklist($list);
		$array = $parser->getNextArray();

		$this->assertTrue(isset($array['user']['name']['first']));
		$this->assertTrue(isset($array['user']['age']));
		$this->assertFalse(isset($array['user']['name']['last']));
		$this->assertFalse(isset($array['user']['sex']));
	}

	public function testWhitelistingColumns()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$list = array('user.name.last', 'user.sex');

		$parser->setWhitelist($list);
		$array = $parser->getNextArray();

		$this->assertNotEmpty($array);	
		$this->assertNotEmpty($array['user']);
		$this->assertEquals(2, count($array['user']));
		$this->assertEquals(1, count($array['user']['name']));
	
		$this->assertFalse(isset($array['user']['name']['first']));
		$this->assertFalse(isset($array['user']['age']));
		$this->assertTrue(isset($array['user']['name']['last']));
		$this->assertTrue(isset($array['user']['sex']));
	}


	public function testWhiteAndBlackListingColumns()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_openFile', '_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$white = array('user.name.last', 'user.sex');
		$black = array('user.sex');

		$parser->setBlacklist($black);
		$parser->setWhitelist($white);
		$array = $parser->getNextArray();

		$this->assertNotEmpty($array);	
		$this->assertNotEmpty($array['user']);
		$this->assertEquals(1, count($array['user']));
		$this->assertEquals(1, count($array['user']['name']));
	
		$this->assertFalse(isset($array['user']['name']['first']));
		$this->assertTrue(isset($array['user']['name']['last']));
		$this->assertFalse(isset($array['user']['age']));
		$this->assertFalse(isset($array['user']['sex']));
	}



	/**
	 * testExceptionIsThrownSettingWhitelist
	 *
	 * @param mixed $parser
	 * @access public
	 * @return void
	 *
	 * @depends testGetNextArrayIncreasesTheRowsFetched
	 * @expectedException JMToolkit_Exception
	 */
	public function testExceptionIsThrownSettingWhitelist($parser)
	{
		$parser->setWhitelist(array());
	}


	/**
	 * testExceptionIsThrownSettingWhitelist
	 *
	 * @param mixed $parser
	 * @access public
	 * @return void
	 *
	 * @depends testGetNextArrayIncreasesTheRowsFetched
	 * @expectedException JMToolkit_Exception
	 */
	public function testExceptionIsThrownSettingBlacklist($parser)
	{
		$parser->setBlacklist(array());
	}

	/**
	 * testExceptionIsThrownSettingWhitelist
	 *
	 * @param mixed $parser
	 * @access public
	 * @return void
	 *
	 * @depends testGetNextArrayIncreasesTheRowsFetched
	 * @expectedException JMToolkit_Exception
	 */
	public function testExceptionIsThrownSettingColumnMap($parser)
	{
		$parser->setColumnMap(array());
	}

	/**
	 * testFileWithNoHeaderRow
	 *
	 * @access public
	 * @return void
	 *
	 * @expectedException JMToolkit_Exception
	 */
	public function testFileWithNoHeaderRow()
	{
		$parser = $this->getmock('JMToolkit_CSV_Parser', array('_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(false));

		$array = $parser->getNextArray();
	}

	public function testCleanMethod()
	{
		$parser = $this->getmock('ParserTestHelper', array('_readFileRow'));

		$parser->expects($this->any())
			->method('_readFileRow')
			->will($this->returnValue(str_getcsv($this->_csv)));

		$array = $parser->getNextArray();

		$array = array();

		$array['user']['name']['first'] = "user.name.first";
		$array['user']['name']['last'] = "user.name.last";
		$array['user']['age'] = "user.age";
		$array['user']['sex'] = "user.sex";

		$this->assertAttributeEquals($array, '_array', $parser);

		$parser->testMethod('_clean');

		$array['user']['name']['first'] = null;
		$array['user']['name']['last'] = null;
		$array['user']['age'] = null;
		$array['user']['sex'] = null;

		$this->assertAttributeEquals($array, '_array', $parser);
	}

}

class ParserTestHelper extends JMToolkit_CSV_Parser
{
	public function testMethod($name, $args=array())
	{
		return call_user_func_array(array($this, $name), $args);
	}
}
