<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

use JMToolkit\Csv\Record\Dialect;
use JMToolkit\Csv\Record\RecordReader;
use JMToolkit\Csv\Record\StreamReader;
use JMToolkit\Csv\Record\ValidatingReader;
use JMToolkit\Csv\Row\ColumnSelection;
use JMToolkit\Csv\Row\RowHydrator;
use JMToolkit\Csv\Row\Schema;
use JMToolkit\Exception;

/**
 * Parses a CSV file into nested arrays (or objects), using the header row to
 * define keys with dot (.) separated namespacing.
 *
 * <code>
 * user.firstname,user.lastname,user.email.work,user.email.home
 * John,Doe,work@email.com,home@email.com
 * </code>
 *
 * becomes
 *
 * <code>
 * $row['user']['firstname']     === 'John'
 * $row['user']['lastname']      === 'Doe'
 * $row['user']['email']['work'] === 'work@email.com'
 * $row['user']['email']['home'] === 'home@email.com'
 * </code>
 *
 * @author    James Moran <moranjk75@gmail.com>
 * @copyright 2011 James Moran
 * @license   MIT
 *
 * @implements \IteratorAggregate<int, array<array-key, mixed>|\stdClass>
 */
final class Parser implements \IteratorAggregate
{
    private const array OPTIONS = ['whitelist', 'blacklist', 'column_map', 'delimiter', 'enclosure', 'escape', 'row_format'];

    /** Number of data rows returned since the current file was set. */
    public private(set) int $rowsFetched = 0;

    private readonly Dialect $dialect;

    private ColumnSelection $columns;

    private RowFormat $rowFormat;

    private ?RecordReader $reader = null;

    /** Compiled from the header row; null until it has been read. */
    private ?Schema $schema = null;

    /** Builds rows from the schema; null until the header row has been read. */
    private ?RowHydrator $hydrator = null;

    /**
     * @param array{
     *     whitelist?: list<string>|null,
     *     blacklist?: list<string>|null,
     *     column_map?: array<string, string>|null,
     *     delimiter?: non-empty-string,
     *     enclosure?: non-empty-string,
     *     escape?: string,
     *     row_format?: RowFormat,
     * } $options
     */
    public function __construct(?string $path = null, array $options = [])
    {
        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            throw new Exception(sprintf('Unknown option(s): %s', implode(', ', $unknown)));
        }

        $rowFormat = $options['row_format'] ?? RowFormat::Array;
        // @phpstan-ignore instanceof.alwaysTrue (options arrive untyped at runtime)
        if (!$rowFormat instanceof RowFormat) {
            throw new Exception(sprintf('Option row_format must be a %s case', RowFormat::class));
        }

        $this->rowFormat = $rowFormat;
        $this->dialect = new Dialect(
            $options['delimiter'] ?? ',',
            $options['enclosure'] ?? '"',
            $options['escape'] ?? '',
        );
        $this->columns = new ColumnSelection(
            $options['column_map'] ?? null,
            $options['whitelist'] ?? null,
            $options['blacklist'] ?? null,
        );

        if ($path !== null) {
            $this->setFile($path);
        }
    }

    /**
     * Renames header columns before they are split into key paths. A column
     * can be mapped to a dot separated name to nest it.
     *
     * @param array<string, string> $map original header => new header
     */
    public function setColumnMap(array $map): static
    {
        $this->assertNotStarted('map');
        $this->columns = $this->columns->withMap($map);

        return $this;
    }

    /**
     * Only columns in this list are used. Names are matched after the column
     * map has been applied.
     *
     * @param list<string> $list
     */
    public function setWhitelist(array $list): static
    {
        $this->assertNotStarted('list');
        $this->columns = $this->columns->withWhitelist($list);

        return $this;
    }

    /**
     * Columns in this list are skipped. Names are matched after the column
     * map has been applied.
     *
     * @param list<string> $list
     */
    public function setBlacklist(array $list): static
    {
        $this->assertNotStarted('list');
        $this->columns = $this->columns->withBlacklist($list);

        return $this;
    }

    /**
     * Chooses whether iterating yields nested arrays or objects. Can be
     * changed at any time; it applies to the rows read afterwards.
     */
    public function setRowFormat(RowFormat $format): static
    {
        $this->rowFormat = $format;
        $this->hydrator = $this->schema === null ? null : $format->hydrator($this->schema);

        return $this;
    }

    /**
     * Opens the CSV file at the given path and resets the parser state.
     */
    public function setFile(string $path): static
    {
        return $this->setReader(StreamReader::open($path, $this->dialect));
    }

    /**
     * Reads from an already open stream (e.g. php://stdin or php://memory)
     * and resets the parser state. The caller remains responsible for
     * closing the stream.
     *
     * @param resource $stream
     */
    public function setStream($stream): static
    {
        return $this->setReader(new StreamReader($stream, $this->dialect));
    }

    /**
     * Iterates over the remaining rows, as nested arrays or objects
     * depending on the row format. Keys are 0-based row numbers. Breaking
     * out of a loop and iterating again continues with the next row.
     *
     * @return \Generator<int, array<array-key, mixed>|\stdClass>
     */
    #[\Override]
    public function getIterator(): \Generator
    {
        while (true) {
            // Read on every pass: setFile() and setRowFormat() may be called mid-loop.
            $reader = $this->reader ?? throw new Exception('No file has been provided');
            $hydrator = $this->hydrator ?? $this->readHeader($reader);

            $record = $reader->read();
            if ($record === null) {
                return;
            }

            /** @var array<array-key, mixed>|\stdClass $row */
            $row = $hydrator->hydrate($record);

            yield $this->rowsFetched++ => $row;
        }
    }

    private function setReader(RecordReader $reader): static
    {
        $this->reader = new ValidatingReader($reader);
        $this->schema = null;
        $this->hydrator = null;
        $this->rowsFetched = 0;

        return $this;
    }

    private function readHeader(RecordReader $reader): RowHydrator
    {
        $headers = $reader->read() ?? throw new Exception('File is missing header row');

        $this->schema = Schema::compile($this->columns->resolve($headers));

        return $this->hydrator = $this->rowFormat->hydrator($this->schema);
    }

    private function assertNotStarted(string $what): void
    {
        if ($this->schema !== null) {
            throw new Exception(sprintf('Attempting to set %s after parsing has started', $what));
        }
    }
}
