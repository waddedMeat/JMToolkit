<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

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
    private const array DEFAULT_OPTIONS = [
        'whitelist' => null,
        'blacklist' => null,
        'column_map' => null,
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '',
        'row_format' => RowFormat::Array,
    ];

    /** Number of data rows returned since the current file was set. */
    public private(set) int $rowsFetched = 0;

    /**
     * @var array{
     *     whitelist: list<string>|null,
     *     blacklist: list<string>|null,
     *     column_map: array<string, string>|null,
     *     delimiter: string,
     *     enclosure: string,
     *     escape: string,
     *     row_format: RowFormat,
     * }
     */
    private array $options;

    /** @var resource|null */
    private $stream = null;

    /** Whether the stream was opened by this parser (and must be closed by it). */
    private bool $ownsStream = false;

    /** Number of columns in the header row; every data row must match it. */
    private int $columnCount = 0;

    /** Number of records read from the stream, header included. */
    private int $recordNumber = 0;

    /**
     * Key path for each kept column, indexed by column position.
     * Null until the header row has been read.
     *
     * @var array<int, non-empty-list<string>>|null
     */
    private ?array $columns = null;

    /**
     * @param array{
     *     whitelist?: list<string>|null,
     *     blacklist?: list<string>|null,
     *     column_map?: array<string, string>|null,
     *     delimiter?: string,
     *     enclosure?: string,
     *     escape?: string,
     *     row_format?: RowFormat,
     * } $options
     */
    public function __construct(?string $path = null, array $options = [])
    {
        $unknown = array_diff_key($options, self::DEFAULT_OPTIONS);
        if ($unknown !== []) {
            throw new Exception(sprintf('Unknown option(s): %s', implode(', ', array_keys($unknown))));
        }

        // @phpstan-ignore instanceof.alwaysTrue (options arrive untyped at runtime)
        if (!(($options['row_format'] ?? RowFormat::Array) instanceof RowFormat)) {
            throw new Exception(sprintf('Option row_format must be a %s case', RowFormat::class));
        }

        $this->options = [...self::DEFAULT_OPTIONS, ...$options];

        if ($path !== null) {
            $this->setFile($path);
        }
    }

    public function __destruct()
    {
        $this->closeStream();
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
        $this->options['column_map'] = $map;

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
        $this->options['whitelist'] = $list;

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
        $this->options['blacklist'] = $list;

        return $this;
    }

    /**
     * Chooses whether iterating yields nested arrays or objects. Can be
     * changed at any time; it applies to the rows read afterwards.
     */
    public function setRowFormat(RowFormat $format): static
    {
        $this->options['row_format'] = $format;

        return $this;
    }

    /**
     * Opens the CSV file at the given path and resets the parser state.
     */
    public function setFile(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new Exception(sprintf('File "%s" does not exist or is not readable.', $path));
        }

        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw new Exception(sprintf('Unable to open file "%s".', $path));
        }

        $this->replaceStream($stream, owned: true);

        return $this;
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
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new Exception('Expected an open stream resource.');
        }

        $this->replaceStream($stream, owned: false);

        return $this;
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
        while (($row = $this->readRow()) !== null) {
            yield $this->rowsFetched - 1 => match ($this->options['row_format']) {
                RowFormat::Array => $row,
                RowFormat::Object => (object) array_map(self::toObject(...), $row),
            };
        }
    }

    /**
     * Reads the next row as a nested array, or null at end of file.
     *
     * @return array<array-key, mixed>|null
     */
    private function readRow(): ?array
    {
        $this->columns ??= $this->readHeader();

        $record = $this->readRecord();
        if ($record === null) {
            return null;
        }

        $row = [];
        foreach ($this->columns as $index => $path) {
            $node = &$row;
            foreach ($path as $key) {
                // @phpstan-ignore offsetAccess.nonOffsetAccessible (readHeader() rejects paths that would nest under a value)
                $node = &$node[$key];
            }
            $node = $record[$index];
            unset($node);
        }

        $this->rowsFetched++;

        /** @var array<array-key, mixed> $row */
        return $row;
    }

    /**
     * @param resource $stream
     */
    private function replaceStream($stream, bool $owned): void
    {
        $this->closeStream();

        $this->stream = $stream;
        $this->ownsStream = $owned;
        $this->rowsFetched = 0;
        $this->recordNumber = 0;
        $this->columnCount = 0;
        $this->columns = null;
    }

    private function closeStream(): void
    {
        if ($this->ownsStream && is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
        $this->ownsStream = false;
    }

    private function assertNotStarted(string $what): void
    {
        if ($this->columns !== null) {
            throw new Exception(sprintf('Attempting to set %s after parsing has started', $what));
        }
    }

    /**
     * Reads the header row and resolves the key path of every kept column.
     *
     * @return array<int, non-empty-list<string>>
     */
    private function readHeader(): array
    {
        $headers = $this->readRecord() ?? throw new Exception('File is missing header row');

        $columns = [];
        $seen = [];
        foreach ($headers as $index => $header) {
            $name = $this->options['column_map'][(string) $header] ?? (string) $header;

            if (!$this->isWhitelisted($name) || $this->isBlacklisted($name)) {
                continue;
            }

            $this->assertNoConflict($name, $seen);
            $seen[$name] = true;
            $columns[$index] = explode('.', $name);
        }

        return $columns;
    }

    /**
     * Rejects a column whose key path would collide with one already seen,
     * e.g. duplicate names, or "user" alongside "user.name".
     *
     * @param array<string, true> $seen
     */
    private function assertNoConflict(string $name, array $seen): void
    {
        foreach (array_keys($seen) as $other) {
            $other = (string) $other;
            if (
                $name === $other
                || str_starts_with($name, $other . '.')
                || str_starts_with($other, $name . '.')
            ) {
                throw new Exception(sprintf('Column "%s" conflicts with column "%s"', $name, $other));
            }
        }
    }

    /**
     * Reads the next non-blank record, or null at end of file.
     *
     * @return list<string|null>|null
     */
    private function readRecord(): ?array
    {
        if ($this->stream === null) {
            throw new Exception('No file has been provided');
        }

        do {
            $record = fgetcsv(
                $this->stream,
                null,
                $this->options['delimiter'],
                $this->options['enclosure'],
                $this->options['escape'],
            );
            if ($record === false) {
                return null;
            }
            $this->recordNumber++;
        } while ($record === [null]);

        $size = count($record);
        if ($this->columnCount === 0) {
            $this->columnCount = $size;
        } elseif ($size !== $this->columnCount) {
            throw new Exception(sprintf(
                'Invalid file: record %d has %d columns, expected %d',
                $this->recordNumber,
                $size,
                $this->columnCount,
            ));
        }

        return $record;
    }

    private function isWhitelisted(string $name): bool
    {
        return $this->options['whitelist'] === null || in_array($name, $this->options['whitelist'], true);
    }

    private function isBlacklisted(string $name): bool
    {
        return $this->options['blacklist'] !== null && in_array($name, $this->options['blacklist'], true);
    }

    /**
     * Converts nested associative arrays to stdClass objects, keeping lists
     * as arrays (the same shape json_decode() would produce).
     */
    private static function toObject(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $converted = array_map(self::toObject(...), $value);

        return array_is_list($value) ? $converted : (object) $converted;
    }
}
