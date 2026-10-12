<?php

declare(strict_types=1);

namespace JMToolkit\Csv\Row;

use JMToolkit\Exception;

/**
 * The shape of a row, compiled once from the header: nested keys from the
 * dot separated column names, whose leaves are the column positions holding
 * their values.
 *
 * @internal
 */
final readonly class Schema
{
    /**
     * @param array<array-key, mixed> $tree
     */
    private function __construct(public array $tree)
    {
    }

    /**
     * @param array<int, string> $columns column position => dot separated name
     *
     * @throws Exception when two names collide, e.g. duplicates, or "user"
     *                   alongside "user.name"
     */
    public static function compile(array $columns): self
    {
        $tree = [];
        $leaves = [];
        $branches = [];

        foreach ($columns as $position => $name) {
            $path = explode('.', $name);

            // Another column already uses this name, as a value or a parent.
            $conflict = $leaves[$name] ?? $branches[$name] ?? null;

            // Record each parent of this column, checking none is a value.
            $prefix = null;
            foreach (array_slice($path, 0, -1) as $key) {
                $prefix = $prefix === null ? $key : $prefix . '.' . $key;
                $conflict ??= $leaves[$prefix] ?? null;
                $branches[$prefix] ??= $name;
            }

            if ($conflict !== null) {
                throw new Exception(sprintf('Column "%s" conflicts with column "%s"', $name, $conflict));
            }

            $leaves[$name] = $name;
            $tree = self::insert($tree, $path, $position);
        }

        return new self($tree);
    }

    /**
     * @param array<array-key, mixed> $node
     * @param list<string> $path
     *
     * @return array<array-key, mixed>
     */
    private static function insert(array $node, array $path, int $position): array
    {
        $key = array_shift($path);
        if ($key === null) {
            return $node;
        }

        $child = $node[$key] ?? [];
        // compile() guarantees a branch is never inserted under a leaf.
        $node[$key] = $path === [] ? $position : self::insert(is_array($child) ? $child : [], $path, $position);

        return $node;
    }
}
