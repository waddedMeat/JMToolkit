<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Record;

use JMToolkit\Exception;

/**
 * Reads CSV records from a stream, producing exactly what fgetcsv() would.
 *
 * fgetcsv() is slow (it inspects every byte through the locale's multibyte
 * rules), so well-formed lines are tokenized here instead: lines without an
 * enclosure character are split with explode(), and lines with properly
 * enclosed fields are matched with a strict regular expression. Anything
 * else (fields spanning several lines, stray enclosure characters, an escape
 * character, or a stream that cannot seek back) is handed to fgetcsv().
 *
 * @internal
 */
final class StreamReader implements RecordReader
{
    /** Strict pattern matching one field per match, or null when only fgetcsv() is used. */
    private readonly ?string $pattern;

    // Copied out of the dialect because read() is the hot path.
    /** @var non-empty-string */
    private readonly string $delimiter;
    /** @var non-empty-string */
    private readonly string $enclosure;
    private readonly string $escape;

    /** Whether the stream was opened by open() and must be closed by this reader. */
    private bool $ownsStream = false;

    /**
     * Reads from an already open stream. The caller remains responsible for
     * closing it.
     *
     * @param resource $stream
     */
    public function __construct(private readonly mixed $stream, Dialect $dialect = new Dialect())
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new Exception('Expected an open stream resource.');
        }

        $this->delimiter = $delimiter = $dialect->delimiter;
        $this->enclosure = $enclosure = $dialect->enclosure;
        $this->escape = $dialect->escape;

        $fastPath = $this->escape === ''
            && $delimiter !== $enclosure
            && !str_contains("\r\n", $delimiter)
            && !str_contains("\r\n", $enclosure)
            && stream_get_meta_data($stream)['seekable'];

        if ($fastPath) {
            $d = preg_quote($delimiter, '/');
            $e = preg_quote($enclosure, '/');
            // Each match is a field: the first at the start of the line, every
            // other one after a delimiter. \G keeps the matches contiguous.
            $this->pattern = "/\\G(?:^|{$d})(?:{$e}((?:[^{$e}]++|{$e}{$e})*+){$e}|([^{$d}{$e}\\r\\n]*+))/";
        } else {
            $this->pattern = null;
        }
    }

    /**
     * Opens the file at the given path; it is closed when the reader is
     * destroyed.
     */
    public static function open(string $path, Dialect $dialect = new Dialect()): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new Exception(sprintf('File "%s" does not exist or is not readable.', $path));
        }

        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw new Exception(sprintf('Unable to open file "%s".', $path));
        }

        $reader = new self($stream, $dialect);
        $reader->ownsStream = true;

        return $reader;
    }

    public function __destruct()
    {
        if ($this->ownsStream && is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Returns the next record, [null] for a blank line, or null at end of file.
     *
     * @return non-empty-list<string|null>|null
     */
    #[\Override]
    public function read(): ?array
    {
        if ($this->pattern === null) {
            return $this->fgetcsv();
        }

        $line = fgets($this->stream);
        if ($line === false) {
            return null;
        }

        // Strip one line ending, as fgetcsv() does.
        $length = strlen($line);
        if ($line[$length - 1] === "\n") {
            $length -= ($length > 1 && $line[$length - 2] === "\r") ? 2 : 1;
        } elseif ($line[$length - 1] === "\r") {
            $length--;
        }

        if ($length === 0) {
            return [null];
        }

        $content = substr($line, 0, $length);

        if (!str_contains($content, $this->enclosure)) {
            if (!str_contains($content, "\r")) {
                return explode($this->delimiter, $content);
            }
        } elseif (
            preg_match_all($this->pattern, $content, $matches, PREG_UNMATCHED_AS_NULL) > 0
            && strlen(implode('', $matches[0])) === $length
        ) {
            $fields = [];
            foreach ($matches[1] as $i => $enclosed) {
                $fields[] = $enclosed === null
                    ? (string) $matches[2][$i]
                    : str_replace($this->enclosure . $this->enclosure, $this->enclosure, $enclosed);
            }

            /** @var non-empty-list<string> $fields (preg_match_all() found at least one field) */
            return $fields;
        }

        fseek($this->stream, -strlen($line), SEEK_CUR);

        return $this->fgetcsv();
    }

    /**
     * @return non-empty-list<string|null>|null
     */
    private function fgetcsv(): ?array
    {
        $record = fgetcsv($this->stream, null, $this->delimiter, $this->enclosure, $this->escape);

        return $record === false ? null : $record;
    }
}
