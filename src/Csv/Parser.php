<?php

declare(strict_types=1);

namespace JMToolkit\Csv;

use JMToolkit\Csv\Column\Blacklist;
use JMToolkit\Csv\Column\ColumnMap;
use JMToolkit\Csv\Column\ColumnSelection;
use JMToolkit\Csv\Column\Whitelist;
use JMToolkit\Csv\Record\Dialect;
use JMToolkit\Csv\Record\StreamReader;
use JMToolkit\Csv\Record\ValidatingReader;

/**
 * Parses CSV files into nested arrays (or objects), using the header row to
 * define keys with dot (.) separated namespacing.
 *
 * A parser only holds configuration, so one instance can parse any number
 * of files, each through its own Rows.
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
 */
final readonly class Parser
{
    private Dialect $dialect;

    private ColumnSelection $columns;

    /**
     * @param ColumnMap $columnMap renames header columns before they are split into key paths
     * @param Whitelist|null $whitelist only these columns are used (matched after mapping)
     * @param Blacklist|null $blacklist these columns are skipped (matched after mapping)
     * @param non-empty-string $delimiter
     * @param non-empty-string $enclosure
     * @param string $escape a single character, or "" for RFC 4180 (enclosures escaped by doubling them)
     */
    public function __construct(
        ColumnMap $columnMap = new ColumnMap(),
        ?Whitelist $whitelist = null,
        ?Blacklist $blacklist = null,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '',
        private RowFormat $rowFormat = RowFormat::Array,
    ) {
        $this->dialect = new Dialect($delimiter, $enclosure, $escape);
        $this->columns = new ColumnSelection($columnMap, ...array_filter([$whitelist, $blacklist]));
    }

    /**
     * Opens the CSV file at the given path. The file is closed once the
     * returned Rows is no longer used.
     */
    public function parseFile(string $path): Rows
    {
        return $this->rows(StreamReader::open($path, $this->dialect));
    }

    /**
     * Reads from an already open stream (e.g. php://stdin or php://memory),
     * starting at its current position. The caller remains responsible for
     * closing the stream.
     *
     * @param resource $stream
     */
    public function parseStream($stream): Rows
    {
        return $this->rows(new StreamReader($stream, $this->dialect));
    }

    private function rows(StreamReader $reader): Rows
    {
        return new Rows(new ValidatingReader($reader), $this->columns, $this->rowFormat);
    }
}
