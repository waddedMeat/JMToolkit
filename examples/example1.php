<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;

foreach (new Parser()->parseFile(__DIR__ . '/example1.csv') as $row) {
    var_dump($row);
}

$parser = new Parser(rowFormat: RowFormat::Object);

foreach ($parser->parseFile(__DIR__ . '/example1.csv') as $obj) {
    var_dump($obj);
}
