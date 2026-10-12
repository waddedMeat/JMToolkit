<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Row;

/**
 * Builds rows as nested stdClass objects. List-like branches such as
 * "items.0", "items.1" stay arrays, the same shape json_decode() would
 * produce.
 *
 * @internal
 */
final readonly class ObjectHydrator implements RowHydrator
{
    /**
     * The schema tree with every branch as [bool $isList, array $children],
     * so lists are known without inspecting every row.
     *
     * @var array<array-key, mixed>
     */
    private array $tree;

    public function __construct(Schema $schema)
    {
        $this->tree = self::compile($schema->tree)[1];
    }

    /**
     * @param list<string|null> $record
     */
    #[\Override]
    public function hydrate(array $record): \stdClass
    {
        return (object) self::fill($this->tree, false, $record);
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array{bool, array<array-key, mixed>}
     */
    private static function compile(array $node): array
    {
        $children = [];
        foreach ($node as $key => $child) {
            /** @var int|array<array-key, mixed> $child */
            $children[$key] = is_int($child) ? $child : self::compile($child);
        }

        return [array_is_list($node), $children];
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string|null> $record
     *
     * @return array<array-key, mixed>|\stdClass
     */
    private static function fill(array $node, bool $isList, array $record): array|\stdClass
    {
        foreach ($node as $key => $child) {
            /** @var int|array{bool, array<array-key, mixed>} $child */
            $node[$key] = is_int($child) ? $record[$child] : self::fill($child[1], $child[0], $record);
        }

        return $isList ? $node : (object) $node;
    }
}
