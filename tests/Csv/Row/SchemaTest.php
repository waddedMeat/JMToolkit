<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Row;

use JMToolkit\Csv\Row\Schema;
use JMToolkit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
final class SchemaTest extends TestCase
{
    public function testCompilesNestedTreeOfColumnPositions(): void
    {
        $schema = Schema::compile([0 => 'id', 2 => 'user.name.first', 3 => 'user.name.last', 5 => 'tags.0']);

        self::assertSame(
            ['id' => 0, 'user' => ['name' => ['first' => 2, 'last' => 3]], 'tags' => [0 => 5]],
            $schema->tree,
        );
    }

    /**
     * @return iterable<string, array{array<int, string>, string}>
     */
    public static function conflicts(): iterable
    {
        yield 'duplicate' => [['a.b', 'a.b'], 'Column "a.b" conflicts with column "a.b"'];
        yield 'value then parent' => [['user', 'user.name'], 'Column "user.name" conflicts with column "user"'];
        yield 'parent then value' => [['user.name', 'user'], 'Column "user" conflicts with column "user.name"'];
        yield 'deep parent then value' => [['a.b.c', 'x', 'a.b'], 'Column "a.b" conflicts with column "a.b.c"'];
    }

    /**
     * @param array<int, string> $columns
     */
    #[DataProvider('conflicts')]
    public function testRejectsConflictingColumns(array $columns, string $message): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message);
        Schema::compile($columns);
    }

    public function testSiblingsWithCommonPrefixDoNotConflict(): void
    {
        $schema = Schema::compile(['user', 'username', 'user_name.first']);

        self::assertSame(['user' => 0, 'username' => 1, 'user_name' => ['first' => 2]], $schema->tree);
    }
}
