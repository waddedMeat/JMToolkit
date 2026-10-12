<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

use JMToolkit\Csv\Column\ColumnSelection;
use JMToolkit\Csv\Mapping\ClassHydrator;
use JMToolkit\Csv\Mapping\MappingException;
use JMToolkit\Csv\Record\RecordReader;
use JMToolkit\Csv\Row\RowHydrator;
use JMToolkit\Csv\Row\Schema;
use JMToolkit\Exception;

/**
 * The rows of one CSV file, read forward one at a time as you iterate.
 * Keys are 0-based row numbers. Breaking out of a loop and iterating again
 * continues with the next row.
 *
 * Created by Parser::parseFile() and Parser::parseStream().
 *
 * @implements \IteratorAggregate<int, array<array-key, mixed>|\stdClass>
 */
final class Rows implements \IteratorAggregate
{
    /** Number of rows returned so far. */
    public private(set) int $rowsFetched = 0;

    /**
     * The header row as it appears in the file, before the column map or
     * filter is applied. Reading it reads the header row from the file if
     * iteration has not started yet.
     *
     * @var list<string>
     */
    public array $headers {
        get {
            $this->schema ??= $this->readHeader();

            return $this->headerRow;
        }
    }

    /** @var list<string> */
    private array $headerRow = [];

    /** Compiled from the header row; null until it has been read. */
    private ?Schema $schema = null;

    /** Builds rows in the parser's row format; null until first needed. */
    private ?RowHydrator $hydrator = null;

    /**
     * @internal use Parser::parseFile() or Parser::parseStream()
     */
    public function __construct(
        private readonly RecordReader $reader,
        private readonly ColumnSelection $columns,
        private readonly RowFormat $format,
    ) {
    }

    /**
     * @return \Generator<int, array<array-key, mixed>|\stdClass>
     */
    #[\Override]
    public function getIterator(): \Generator
    {
        $hydrator = $this->hydrator ??= $this->format->hydrator($this->schema ??= $this->readHeader());

        while (($record = $this->reader->read()) !== null) {
            /** @var array<array-key, mixed>|\stdClass $row */
            $row = $hydrator->hydrate($record);

            yield $this->rowsFetched++ => $row;
        }
    }

    /**
     * Maps each remaining row onto a new instance of the given class, built
     * through its constructor. Parameters are matched to columns by name
     * (or #[Column]), nested classes are built from nested columns, and
     * values are converted to the parameter types. Like iterating, this
     * continues from the current row; keys are 0-based row numbers.
     *
     * Whether the class fits the file is checked once, when iteration
     * starts; values are checked per row.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return \Generator<int, T>
     *
     * @throws MappingException when the class does not fit the columns, or a
     *                          value does not fit its type (with the row number)
     */
    public function into(string $class): \Generator
    {
        $hydrator = new ClassHydrator($this->schema ??= $this->readHeader(), $class);

        while (($record = $this->reader->read()) !== null) {
            try {
                $object = $hydrator->hydrate($record);
            } catch (MappingException $e) {
                throw $e->inRow($this->rowsFetched + 1);
            }

            yield $this->rowsFetched++ => $object;
        }
    }

    private function readHeader(): Schema
    {
        $headers = $this->reader->read() ?? throw new Exception('File is missing header row');
        $this->headerRow = array_map(strval(...), $headers);

        return Schema::compile($this->columns->resolve($headers));
    }
}
