<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Column;

use JMToolkit\Csv\Column\AllOf;
use JMToolkit\Csv\Column\Denylist;
use JMToolkit\Csv\Column\PatternFilter;
use JMToolkit\Csv\Column\ColumnMap;
use JMToolkit\Csv\Column\ColumnSelection;
use JMToolkit\Csv\Column\Allowlist;
use JMToolkit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AllOf::class)]
#[CoversClass(ColumnMap::class)]
#[CoversClass(Allowlist::class)]
#[CoversClass(Denylist::class)]
#[CoversClass(PatternFilter::class)]
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

    public function testAllowlist(): void
    {
        $list = new Allowlist('a', '0');

        self::assertTrue($list->accepts('a'));
        self::assertTrue($list->accepts('0'));
        self::assertFalse($list->accepts('b'));
        self::assertFalse(new Allowlist()->accepts('a'));
    }

    public function testDenylist(): void
    {
        $list = new Denylist('a', '0');

        self::assertFalse($list->accepts('a'));
        self::assertFalse($list->accepts('0'));
        self::assertTrue($list->accepts('b'));
        self::assertTrue(new Denylist()->accepts('a'));
    }

    public function testPatternFilter(): void
    {
        $filter = new PatternFilter('/^user\./');

        self::assertTrue($filter->accepts('user.name'));
        self::assertTrue($filter->accepts('user.email.work'));
        self::assertFalse($filter->accepts('user'));
        self::assertFalse($filter->accepts('admin.user.name'));
    }

    public function testPatternFilterSupportsModifiers(): void
    {
        self::assertTrue(new PatternFilter('/^USER\./i')->accepts('user.name'));
        self::assertTrue(new PatternFilter('/^é/u')->accepts('été'));
    }

    public function testInvalidPatternThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid pattern "/(unclosed/": Compilation failed: missing closing parenthesis');
        new PatternFilter('/(unclosed/');
    }

    public function testPatternWithoutDelimitersThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid pattern "user"');
        new PatternFilter('user');
    }

    public function testAllOfAcceptsOnlyWhatEveryFilterAccepts(): void
    {
        $filter = new AllOf(new Allowlist('a', 'b'), new Denylist('b'));

        self::assertTrue($filter->accepts('a'));
        self::assertFalse($filter->accepts('b'));
        self::assertFalse($filter->accepts('c'));
        self::assertTrue(new AllOf()->accepts('anything'));
    }

    public function testSelectionMapsThenFilters(): void
    {
        $selection = new ColumnSelection(
            new ColumnMap()->rename('first', 'name.first')->rename('pw', 'secret'),
            new AllOf(new Allowlist('name.first', 'secret', 'age'), new Denylist('secret')),
        );

        self::assertSame([0 => 'name.first', 3 => 'age'], $selection->resolve(['first', 'last', 'pw', 'age']));
    }

    public function testSelectionWithoutFiltersKeepsEveryColumn(): void
    {
        self::assertSame([0 => 'a', 1 => '', 2 => 'c'], new ColumnSelection()->resolve(['a', null, 'c']));
    }
}
