<?php

require_once "../CSV/Parser.php";
require_once "../Exception.php";

$parser = new JMToolkit_CSV_Parser();
$parser->setFile('example1.csv');

while ($array = $parser->getNextArray()) {
	var_dump($array);
}

$parser->setFile('example1.csv');
while ($obj = $parser->getNextObject()) {
	var_dump($obj);
}


