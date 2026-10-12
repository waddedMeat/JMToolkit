<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

use JMToolkit\Csv\Record\RecordReader;
use JMToolkit\Csv\Row\ColumnSelection;
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
     * The header row as it appears in the file, before any column map,
     * whitelist or blacklist is applied. Reading it reads the header row
     * from the file if iteration has not started yet.
     *
     * @var list<string>
     */
    public array $headers {
        get {
            $this->hydrator ??= $this->readHeader();

            return $this->headerRow;
        }
    }

    /** @var list<string> */
    private array $headerRow = [];

    /** Builds rows; null until the header row has been read. */
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
        $hydrator = $this->hydrator ??= $this->readHeader();

        while (($record = $this->reader->read()) !== null) {
            /** @var array<array-key, mixed>|\stdClass $row */
            $row = $hydrator->hydrate($record);

            yield $this->rowsFetched++ => $row;
        }
    }

    private function readHeader(): RowHydrator
    {
        $headers = $this->reader->read() ?? throw new Exception('File is missing header row');
        $this->headerRow = array_map(strval(...), $headers);

        return $this->format->hydrator(Schema::compile($this->columns->resolve($headers)));
    }
}
