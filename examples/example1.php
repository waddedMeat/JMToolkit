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

// Rows can also be mapped onto your own classes.
final readonly class Name
{
    public function __construct(public string $first, public string $last)
    {
    }
}

final readonly class Person
{
    public function __construct(public Name $name, public int $age, public string $sex)
    {
    }
}

final readonly class Row
{
    public function __construct(public Person $user)
    {
    }
}

foreach ($parser->parseFile(__DIR__ . '/example1.csv')->into(Row::class) as $row) {
    printf("%s %s is %d\n", $row->user->name->first, $row->user->name->last, $row->user->age);
}
