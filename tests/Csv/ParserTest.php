<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv;

use JMToolkit\Csv\Column\AllOf;
use JMToolkit\Csv\Column\Blacklist;
use JMToolkit\Csv\Column\ColumnFilter;
use JMToolkit\Csv\Column\ColumnMap;
use JMToolkit\Csv\Column\Whitelist;
use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;
use JMToolkit\Csv\Rows;
use JMToolkit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[CoversClass(Rows::class)]
final class ParserTest extends TestCase
{
    private const string HEADER = '"user.name.first","user.name.last","user.age","user.sex"';

    private const string USERS = self::HEADER . "\n"
        . "James,Moran,37,M\n"
        . "John,Doe,18,M\n"
        . "Jane,Doe,19,F\n";

    private const string EXAMPLE = __DIR__ . '/../../examples/example1.csv';

    /** @var list<resource> */
    private array $streams = [];

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->streams as $stream) {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $this->streams = [];
    }

    /**
     * @return resource
     */
    private function stream(string $csv)
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);
        $this->streams[] = $stream;

        return $stream;
    }

    private function rows(string $csv, Parser $parser = new Parser()): Rows
    {
        return $parser->parseStream($this->stream($csv));
    }

    /**
     * Reads the next row, the way a single foreach step would.
     *
     * @return array<array-key, mixed>|\stdClass|null
     */
    private static function next(Rows $rows): array|\stdClass|null
    {
        $iterator = $rows->getIterator();

        return $iterator->valid() ? $iterator->current() : null;
    }

    public function testParsesNestedRows(): void
    {
        $rows = $this->rows(self::USERS);

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            self::next($rows),
        );
        self::assertSame(1, $rows->rowsFetched);
    }

    public function testParsesFile(): void
    {
        $rows = iterator_to_array(new Parser()->parseFile(self::EXAMPLE));

        self::assertCount(3, $rows);
        self::assertSame(['user' => ['name' => ['first' => 'Jane', 'last' => 'Doe'], 'age' => '19', 'sex' => 'F']], $rows[2]);
    }

    public function testIterationStopsAtEndOfFile(): void
    {
        $rows = $this->rows(self::USERS);

        self::assertSame(3, iterator_count($rows));
        self::assertSame(0, iterator_count($rows));
        self::assertSame(3, $rows->rowsFetched);
    }

    public function testIterationResumesAfterBreak(): void
    {
        $rows = $this->rows(self::USERS);

        foreach ($rows as $row) {
            break;
        }

        self::assertSame([1, 2], array_keys(iterator_to_array($rows)));
    }

    public function testReturnedRowsAreIndependent(): void
    {
        [$first, $second] = iterator_to_array($this->rows(self::USERS));

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            $first,
        );
        self::assertSame(
            ['user' => ['name' => ['first' => 'John', 'last' => 'Doe'], 'age' => '18', 'sex' => 'M']],
            $second,
        );
    }

    public function testKeysAreRowNumbers(): void
    {
        $users = [];
        foreach ($this->rows(self::USERS) as $index => $row) {
            self::assertIsArray($row);
            $users[$index] = $row['user'];
        }

        self::assertSame([0, 1, 2], array_keys($users));
        self::assertSame(['name' => ['first' => 'Jane', 'last' => 'Doe'], 'age' => '19', 'sex' => 'F'], $users[2]);
    }

    public function testOneParserReadsSeveralFilesIndependently(): void
    {
        $parser = new Parser(filter: new Whitelist('a'));
        $narrow = $this->rows("a,b\n1,2\n3,4\n", $parser);
        $wide = $this->rows("a,b,c\n5,6,7\n", $parser);

        self::assertSame(['a' => '1'], self::next($narrow));
        self::assertSame(['a' => '5'], self::next($wide));
        self::assertSame(['a' => '3'], self::next($narrow));
        self::assertSame(2, $narrow->rowsFetched);
        self::assertSame(1, $wide->rowsFetched);
    }

    public function testHeaders(): void
    {
        $rows = $this->rows(
            "first,last,age\nJames,Moran,37\n",
            new Parser(columnMap: new ColumnMap()->rename('first', 'name.first'), filter: new Blacklist('age')),
        );

        self::assertSame(['first', 'last', 'age'], $rows->headers);
        self::assertSame(['name' => ['first' => 'James'], 'last' => 'Moran'], self::next($rows));
        self::assertSame(['first', 'last', 'age'], $rows->headers);
    }

    public function testHeaderOnlyFileHasNoRows(): void
    {
        $rows = $this->rows(self::HEADER . "\n");

        self::assertNull(self::next($rows));
        self::assertSame(['user.name.first', 'user.name.last', 'user.age', 'user.sex'], $rows->headers);
    }

    public function testSkipsBlankLines(): void
    {
        self::assertSame(1, iterator_count($this->rows(self::HEADER . "\n\nJames,Moran,37,M\n\n")));
    }

    public function testObjectRowFormat(): void
    {
        $rows = iterator_to_array($this->rows(
            "categories.0.name,categories.1.name,meta.0\nfoo,bar,baz\n",
            new Parser(rowFormat: RowFormat::Object),
        ));

        self::assertEquals(
            [(object) [
                'categories' => [(object) ['name' => 'foo'], (object) ['name' => 'bar']],
                'meta' => ['baz'],
            ]],
            $rows,
        );
    }

    public function testObjectRowFormatHandlesNonUtf8Data(): void
    {
        $row = self::next($this->rows("name\n\xE9t\xE9\n", new Parser(rowFormat: RowFormat::Object)));

        self::assertEquals((object) ['name' => "\xE9t\xE9"], $row);
    }

    public function testNumericKeysArePreserved(): void
    {
        $row = self::next($this->rows(
            "categories.0.name,categories.1.name,categories.0.description,categories.1.description\na,b,c,d\n",
        ));

        self::assertSame(
            ['categories' => [0 => ['name' => 'a', 'description' => 'c'], 1 => ['name' => 'b', 'description' => 'd']]],
            $row,
        );
    }

    public function testColumnMap(): void
    {
        $row = self::next($this->rows(
            "firstname,lastname,age,user.sex\nJames,Moran,37,M\n",
            new Parser(
                columnMap: new ColumnMap()
                    ->rename('firstname', 'user.name.first')
                    ->rename('lastname', 'user.name.last')
                    ->rename('age', 'user.age'),
            ),
        ));

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            $row,
        );
    }

    public function testBlacklist(): void
    {
        $row = self::next($this->rows(self::USERS, new Parser(filter: new Blacklist('user.name.last', 'user.sex'))));

        self::assertSame(['user' => ['name' => ['first' => 'James'], 'age' => '37']], $row);
    }

    public function testWhitelist(): void
    {
        $row = self::next($this->rows(self::USERS, new Parser(filter: new Whitelist('user.name.last', 'user.sex'))));

        self::assertSame(['user' => ['name' => ['last' => 'Moran'], 'sex' => 'M']], $row);
    }

    public function testWhitelistAndBlacklist(): void
    {
        $row = self::next($this->rows(
            self::USERS,
            new Parser(filter: new AllOf(new Whitelist('user.name.last', 'user.sex'), new Blacklist('user.sex'))),
        ));

        self::assertSame(['user' => ['name' => ['last' => 'Moran']]], $row);
    }

    public function testCustomFilter(): void
    {
        $startsWithUserName = new class implements ColumnFilter {
            public function accepts(string $name): bool
            {
                return str_starts_with($name, 'user.name.');
            }
        };

        $row = self::next($this->rows(self::USERS, new Parser(filter: $startsWithUserName)));

        self::assertSame(['user' => ['name' => ['first' => 'James', 'last' => 'Moran']]], $row);
    }

    public function testListsMatchMappedNames(): void
    {
        $row = self::next($this->rows(
            "first,last\nJames,Moran\n",
            new Parser(columnMap: new ColumnMap()->rename('first', 'name.first'), filter: new Whitelist('name.first')),
        ));

        self::assertSame(['name' => ['first' => 'James']], $row);
    }

    public function testCustomDelimiterAndEnclosure(): void
    {
        $row = self::next($this->rows("'a.b';'a.c'\n'x;y';z\n", new Parser(delimiter: ';', enclosure: "'")));

        self::assertSame(['a' => ['b' => 'x;y', 'c' => 'z']], $row);
    }

    public function testBackslashIsNotAnEscapeCharacterByDefault(): void
    {
        $row = self::next($this->rows("a,b\n\"C:\\dir\\\",x\n"));

        self::assertSame(['a' => 'C:\\dir\\', 'b' => 'x'], $row);
    }

    public function testMissingHeaderRow(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing header row');
        self::next($this->rows(''));
    }

    public function testMissingHeaderRowWhenReadingHeaders(): void
    {
        $rows = $this->rows('');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing header row');
        self::assertNotEmpty($rows->headers);
    }

    public function testColumnCountMismatch(): void
    {
        $rows = $this->rows("a,b,c\n1,2,3\n1,2\n");
        self::next($rows);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('record 3 has 2 columns, expected 3');
        self::next($rows);
    }

    public function testDuplicateColumnsAreRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column "a.b" conflicts with column "a.b"');
        self::next($this->rows("a.b,a.b\n1,2\n"));
    }

    public function testLeafAndBranchColumnsAreRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column "user.name" conflicts with column "user"');
        self::next($this->rows("user,user.name\n1,2\n"));
    }

    public function testOpeningFileThatDoesNotExist(): void
    {
        $this->expectException(Exception::class);
        new Parser()->parseFile('john_jacob_jingleheimer_schmidt');
    }

    public function testOpeningDirectoryThrows(): void
    {
        $this->expectException(Exception::class);
        new Parser()->parseFile(__DIR__);
    }

    public function testParseStreamRejectsNonResource(): void
    {
        $this->expectException(Exception::class);
        // @phpstan-ignore argument.type
        new Parser()->parseStream('not a stream');
    }

    /**
     * @return iterable<string, array{\Closure(): Parser}>
     */
    public static function invalidCharacterOptions(): iterable
    {
        // @phpstan-ignore argument.type
        yield 'empty delimiter' => [static fn (): Parser => new Parser(delimiter: '')];
        yield 'long delimiter' => [static fn (): Parser => new Parser(delimiter: '::')];
        // @phpstan-ignore argument.type
        yield 'empty enclosure' => [static fn (): Parser => new Parser(enclosure: '')];
        yield 'long escape' => [static fn (): Parser => new Parser(escape: '\\\\')];
    }

    /**
     * @param \Closure(): Parser $create
     */
    #[DataProvider('invalidCharacterOptions')]
    public function testCharacterOptionsMustBeSingleCharacters(\Closure $create): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a single character');
        $create();
    }
}
