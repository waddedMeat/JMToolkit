<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Column;

use JMToolkit\Csv\Column\Blacklist;
use JMToolkit\Csv\Column\ColumnMap;
use JMToolkit\Csv\Column\ColumnSelection;
use JMToolkit\Csv\Column\Whitelist;
use JMToolkit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnMap::class)]
#[CoversClass(Whitelist::class)]
#[CoversClass(Blacklist::class)]
#[CoversClass(ColumnSelection::class)]
final class ColumnTest extends TestCase
{
    public function testColumnMapRenamesOnlyMappedHeaders(): void
    {
        $map = new ColumnMap()->rename('firstname', 'user.name.first')->rename('0', 'zero');

        self::assertSame('user.name.first', $map->nameOf('firstname'));
        self::assertSame('zero', $map->nameOf('0'));
        self::assertSame('lastname', $map->nameOf('lastname'));
    }

    public function testRenameReturnsANewMap(): void
    {
        $empty = new ColumnMap();
        $map = $empty->rename('a', 'b');

        self::assertNotSame($empty, $map);
        self::assertSame('a', $empty->nameOf('a'));
        self::assertSame('b', $map->nameOf('a'));
    }

    public function testRenamingTheSameColumnTwiceThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column "a" is already renamed to "b"');
        new ColumnMap()->rename('a', 'b')->rename('a', 'c');
    }

    public function testWhitelist(): void
    {
        $list = new Whitelist('a', '0');

        self::assertTrue($list->accepts('a'));
        self::assertTrue($list->accepts('0'));
        self::assertFalse($list->accepts('b'));
        self::assertFalse(new Whitelist()->accepts('a'));
    }

    public function testBlacklist(): void
    {
        $list = new Blacklist('a', '0');

        self::assertFalse($list->accepts('a'));
        self::assertFalse($list->accepts('0'));
        self::assertTrue($list->accepts('b'));
        self::assertTrue(new Blacklist()->accepts('a'));
    }

    public function testSelectionMapsThenFilters(): void
    {
        $selection = new ColumnSelection(
            new ColumnMap()->rename('first', 'name.first')->rename('pw', 'secret'),
            new Whitelist('name.first', 'secret', 'age'),
            new Blacklist('secret'),
        );

        self::assertSame([0 => 'name.first', 3 => 'age'], $selection->resolve(['first', 'last', 'pw', 'age']));
    }

    public function testSelectionWithoutFiltersKeepsEveryColumn(): void
    {
        self::assertSame([0 => 'a', 1 => '', 2 => 'c'], new ColumnSelection()->resolve(['a', null, 'c']));
    }
}
