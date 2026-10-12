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
     *     delimiter: non-empty-string,
     *     enclosure: non-empty-string,
     *     escape: string,
     *     row_format: RowFormat,
     * }
     */
    private array $options;

    /** @var resource|null */
    private $stream = null;

    /** Whether the stream was opened by this parser (and must be closed by it). */
    private bool $ownsStream = false;

    private ?Reader $reader = null;

    /** Number of columns in the header row; every data row must match it. */
    private int $columnCount = 0;

    /** Number of records read from the stream, header included. */
    private int $recordNumber = 0;

    /**
     * Shape of a row, compiled from the header: nested keys whose leaves are
     * the column positions holding their values. Null until the header row
     * has been read.
     *
     * @var array<array-key, mixed>|null
     */
    private ?array $tree = null;

    /**
     * The same shape for RowFormat::Object: each branch is [bool $isList,
     * array $children], so lists can stay arrays without inspecting every row.
     *
     * @var array{bool, array<array-key, mixed>}
     */
    private array $objectTree = [false, []];

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
        $unknown = array_diff_key($options, self::DEFAULT_OPTIONS);
        if ($unknown !== []) {
            throw new Exception(sprintf('Unknown option(s): %s', implode(', ', array_keys($unknown))));
        }

        // @phpstan-ignore instanceof.alwaysTrue (options arrive untyped at runtime)
        if (!(($options['row_format'] ?? RowFormat::Array) instanceof RowFormat)) {
            throw new Exception(sprintf('Option row_format must be a %s case', RowFormat::class));
        }

        foreach (['delimiter', 'enclosure', 'escape'] as $option) {
            $value = $options[$option] ?? self::DEFAULT_OPTIONS[$option];
            if (strlen($value) !== 1 && !($option === 'escape' && $value === '')) {
                throw new Exception(sprintf('Option %s must be a single character', $option));
            }
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
        while (true) {
            $this->tree ??= $this->readHeader();
            $record = $this->readRecord();
            if ($record === null) {
                return;
            }

            yield $this->rowsFetched++ => $this->options['row_format'] === RowFormat::Array
                ? self::buildArray($this->tree, $record)
                : (object) self::buildObject($this->objectTree[1], false, $record);
        }
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>
     */
    private static function buildArray(array $node, array $record): array
    {
        // Overwriting the leaves of a copy of the node keeps the key order
        // and avoids growing a new array one key at a time.
        foreach ($node as $key => $child) {
            /** @var int|array<array-key, mixed> $child */
            $node[$key] = is_int($child) ? $record[$child] : self::buildArray($child, $record);
        }

        return $node;
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>|\stdClass
     */
    private static function buildObject(array $node, bool $isList, array $record): array|\stdClass
    {
        foreach ($node as $key => $child) {
            /** @var int|array{bool, array<array-key, mixed>} $child */
            $node[$key] = is_int($child) ? $record[$child] : self::buildObject($child[1], $child[0], $record);
        }

        return $isList ? $node : (object) $node;
    }

    /**
     * @param resource $stream
     */
    private function replaceStream($stream, bool $owned): void
    {
        $this->closeStream();

        $this->stream = $stream;
        $this->ownsStream = $owned;
        $this->reader = new Reader(
            $stream,
            $this->options['delimiter'],
            $this->options['enclosure'],
            $this->options['escape'],
        );
        $this->rowsFetched = 0;
        $this->recordNumber = 0;
        $this->columnCount = 0;
        $this->tree = null;
    }

    private function closeStream(): void
    {
        if ($this->ownsStream && is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
        $this->reader = null;
        $this->ownsStream = false;
    }

    private function assertNotStarted(string $what): void
    {
        if ($this->tree !== null) {
            throw new Exception(sprintf('Attempting to set %s after parsing has started', $what));
        }
    }

    /**
     * Reads the header row and compiles the shape of every row from it.
     *
     * @return array<array-key, mixed>
     */
    private function readHeader(): array
    {
        $headers = $this->readRecord() ?? throw new Exception('File is missing header row');

        $tree = [];
        $seen = [];
        foreach ($headers as $index => $header) {
            $name = $this->options['column_map'][(string) $header] ?? (string) $header;

            if (!$this->isWhitelisted($name) || $this->isBlacklisted($name)) {
                continue;
            }

            $this->assertNoConflict($name, $seen);
            $seen[$name] = true;

            $tree = self::insert($tree, explode('.', $name), $index);
        }

        $this->objectTree = self::compileObjectTree($tree);

        return $tree;
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string> $path
     *
     * @return array<array-key, mixed>
     */
    private static function insert(array $node, array $path, int $index): array
    {
        $key = array_shift($path);
        if ($key === null) {
            return $node;
        }

        $child = $node[$key] ?? [];
        // assertNoConflict() guarantees a branch is never inserted under a leaf.
        $node[$key] = $path === [] ? $index : self::insert(is_array($child) ? $child : [], $path, $index);

        return $node;
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array{bool, array<array-key, mixed>}
     */
    private static function compileObjectTree(array $node): array
    {
        $children = [];
        foreach ($node as $key => $child) {
            /** @var int|array<array-key, mixed> $child */
            $children[$key] = is_int($child) ? $child : self::compileObjectTree($child);
        }

        return [array_is_list($node), $children];
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
        $reader = $this->reader ?? throw new Exception('No file has been provided');

        do {
            $record = $reader->read();
            if ($record === null) {
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
}
