<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;

$parser = new Parser(__DIR__ . '/example1.csv');

foreach ($parser as $row) {
    var_dump($row);
}

$parser = new Parser(__DIR__ . '/example1.csv', ['row_format' => RowFormat::Object]);

foreach ($parser as $obj) {
    var_dump($obj);
}
