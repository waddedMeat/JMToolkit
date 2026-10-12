<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Row;

/**
 * Builds rows as nested associative arrays.
 *
 * @internal
 */
final readonly class ArrayHydrator implements RowHydrator
{
    /** @var array<array-key, mixed> */
    private array $tree;

    public function __construct(Schema $schema)
    {
        $this->tree = $schema->tree;
    }

    /**
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function hydrate(array $record): array
    {
        return self::fill($this->tree, $record);
    }

    /**
     * Fills a schema subtree with the values of a record.
     *
     * @param array<array-key, mixed> $node
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>
     */
    public static function fill(array $node, array $record): array
    {
        // Overwriting the leaves of a copy of the node keeps the key order
        // and avoids growing a new array one key at a time.
        foreach ($node as $key => $child) {
            /** @var int|array<array-key, mixed> $child */
            $node[$key] = is_int($child) ? $record[$child] : self::fill($child, $record);
        }

        return $node;
    }
}
