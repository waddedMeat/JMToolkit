<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Row;

/**
 * Decides which header columns are used and what they are called.
 *
 * @internal
 */
final readonly class ColumnSelection
{
    /**
     * @param array<string, string>|null $map original header => new name
     * @param list<string>|null $whitelist only these (mapped) names are used
     * @param list<string>|null $blacklist these (mapped) names are skipped
     */
    public function __construct(
        public ?array $map = null,
        public ?array $whitelist = null,
        public ?array $blacklist = null,
    ) {
    }

    /**
     * Applies the map, then the whitelist and blacklist, to a header row.
     *
     * @param list<string|null> $headers
     *
     * @return array<int, string> column position => name, for the kept columns
     */
    public function resolve(array $headers): array
    {
        $columns = [];
        foreach ($headers as $position => $header) {
            $name = $this->map[(string) $header] ?? (string) $header;

            if (
                ($this->whitelist === null || in_array($name, $this->whitelist, true))
                && ($this->blacklist === null || !in_array($name, $this->blacklist, true))
            ) {
                $columns[$position] = $name;
            }
        }

        return $columns;
    }
}
