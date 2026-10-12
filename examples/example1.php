<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JMToolkit\Csv\Parser;

$parser = new Parser(__DIR__ . '/example1.csv');

foreach ($parser as $row) {
    var_dump($row);
}

$parser->setFile(__DIR__ . '/example1.csv');
while (($obj = $parser->getNextObject()) !== null) {
    var_dump($obj);
}
