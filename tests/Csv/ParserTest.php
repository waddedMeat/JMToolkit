<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv;

use JMToolkit\Csv\Parser;
use JMToolkit\Csv\RowFormat;
use JMToolkit\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    private const string HEADER = '"user.name.first","user.name.last","user.age","user.sex"';

    private const string USERS = self::HEADER . "\n"
        . "James,Moran,37,M\n"
        . "John,Doe,18,M\n"
        . "Jane,Doe,19,F\n";

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

    /**
     * Reads the next row, the way a single foreach step would.
     *
     * @return array<array-key, mixed>|\stdClass|null
     */
    private static function next(Parser $parser): array|\stdClass|null
    {
        $rows = $parser->getIterator();

        return $rows->valid() ? $rows->current() : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function parser(string $csv, array $options = []): Parser
    {
        // @phpstan-ignore argument.type (tests pass invalid options on purpose)
        return new Parser(null, $options)->setStream($this->stream($csv));
    }

    public function testParsesNestedRows(): void
    {
        $parser = $this->parser(self::USERS);

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            self::next($parser),
        );
        self::assertSame(1, $parser->rowsFetched);
    }

    public function testIterationStopsAtEndOfFile(): void
    {
        $parser = $this->parser(self::USERS);

        self::assertSame(3, iterator_count($parser));
        self::assertSame(0, iterator_count($parser));
        self::assertSame(3, $parser->rowsFetched);
    }

    public function testIterationResumesAfterBreak(): void
    {
        $parser = $this->parser(self::USERS);

        foreach ($parser as $row) {
            break;
        }

        self::assertSame([1, 2], array_keys(iterator_to_array($parser)));
    }

    public function testReturnedRowsAreIndependent(): void
    {
        [$first, $second] = iterator_to_array($this->parser(self::USERS));

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            $first,
        );
        self::assertSame(
            ['user' => ['name' => ['first' => 'John', 'last' => 'Doe'], 'age' => '18', 'sex' => 'M']],
            $second,
        );
    }

    public function testIsIterable(): void
    {
        $users = [];
        foreach ($this->parser(self::USERS) as $index => $row) {
            self::assertIsArray($row);
            $users[$index] = $row['user'];
        }

        self::assertSame([0, 1, 2], array_keys($users));
        self::assertSame(['name' => ['first' => 'Jane', 'last' => 'Doe'], 'age' => '19', 'sex' => 'F'], $users[2]);
    }

    public function testHeaderOnlyFileHasNoRows(): void
    {
        self::assertNull(self::next($this->parser(self::HEADER . "\n")));
    }

    public function testSkipsBlankLines(): void
    {
        $parser = $this->parser(self::HEADER . "\n\nJames,Moran,37,M\n\n");

        self::assertSame(1, iterator_count($parser));
    }

    public function testObjectRowFormat(): void
    {
        $rows = iterator_to_array($this->parser(
            "categories.0.name,categories.1.name,meta.0\nfoo,bar,baz\n",
            ['row_format' => RowFormat::Object],
        ));

        self::assertEquals(
            [(object) [
                'categories' => [(object) ['name' => 'foo'], (object) ['name' => 'bar']],
                'meta' => ['baz'],
            ]],
            $rows,
        );
    }

    public function testRowFormatCanBeChangedBetweenRows(): void
    {
        $parser = $this->parser(self::USERS);

        self::assertIsArray(self::next($parser));
        self::assertInstanceOf(\stdClass::class, self::next($parser->setRowFormat(RowFormat::Object)));
        self::assertIsArray(self::next($parser->setRowFormat(RowFormat::Array)));
    }

    public function testObjectRowFormatHandlesNonUtf8Data(): void
    {
        $row = self::next($this->parser("name\n\xE9t\xE9\n")->setRowFormat(RowFormat::Object));

        self::assertEquals((object) ['name' => "\xE9t\xE9"], $row);
    }

    public function testInvalidRowFormatThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('row_format');
        $this->parser(self::USERS, ['row_format' => 'object']);
    }

    public function testNumericKeysArePreserved(): void
    {
        $row = self::next($this->parser(
            "categories.0.name,categories.1.name,categories.0.description,categories.1.description\na,b,c,d\n",
        ));

        self::assertSame(
            ['categories' => [0 => ['name' => 'a', 'description' => 'c'], 1 => ['name' => 'b', 'description' => 'd']]],
            $row,
        );
    }

    public function testColumnMap(): void
    {
        $row = self::next($this->parser(
            "firstname,lastname,age,user.sex\nJames,Moran,37,M\n",
            ['column_map' => ['firstname' => 'user.name.first', 'lastname' => 'user.name.last', 'age' => 'user.age']],
        ));

        self::assertSame(
            ['user' => ['name' => ['first' => 'James', 'last' => 'Moran'], 'age' => '37', 'sex' => 'M']],
            $row,
        );
    }

    public function testBlacklist(): void
    {
        $row = self::next($this->parser(self::USERS)->setBlacklist(['user.name.last', 'user.sex']));

        self::assertSame(['user' => ['name' => ['first' => 'James'], 'age' => '37']], $row);
    }

    public function testWhitelist(): void
    {
        $row = self::next($this->parser(self::USERS)->setWhitelist(['user.name.last', 'user.sex']));

        self::assertSame(['user' => ['name' => ['last' => 'Moran'], 'sex' => 'M']], $row);
    }

    public function testWhitelistAndBlacklist(): void
    {
        $row = self::next(
            $this->parser(self::USERS)
                ->setWhitelist(['user.name.last', 'user.sex'])
                ->setBlacklist(['user.sex']),
        );

        self::assertSame(['user' => ['name' => ['last' => 'Moran']]], $row);
    }

    public function testListsMatchMappedNames(): void
    {
        $row = self::next($this->parser(
            "first,last\nJames,Moran\n",
            ['column_map' => ['first' => 'name.first'], 'whitelist' => ['name.first']],
        ));

        self::assertSame(['name' => ['first' => 'James']], $row);
    }

    public function testCustomDelimiterAndEnclosure(): void
    {
        $row = self::next($this->parser("'a.b';'a.c'\n'x;y';z\n", ['delimiter' => ';', 'enclosure' => "'"]));

        self::assertSame(['a' => ['b' => 'x;y', 'c' => 'z']], $row);
    }

    public function testBackslashIsNotAnEscapeCharacterByDefault(): void
    {
        $row = self::next($this->parser("a,b\n\"C:\\dir\\\",x\n"));

        self::assertSame(['a' => 'C:\\dir\\', 'b' => 'x'], $row);
    }

    /**
     * @return iterable<string, array{\Closure(Parser): mixed}>
     */
    public static function settersAfterStart(): iterable
    {
        yield 'whitelist' => [static fn (Parser $p): Parser => $p->setWhitelist([])];
        yield 'blacklist' => [static fn (Parser $p): Parser => $p->setBlacklist([])];
        yield 'column map' => [static fn (Parser $p): Parser => $p->setColumnMap([])];
    }

    /**
     * @param \Closure(Parser): mixed $setter
     */
    #[DataProvider('settersAfterStart')]
    public function testSettersThrowAfterParsingStarted(\Closure $setter): void
    {
        $parser = $this->parser(self::USERS);
        self::next($parser);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('after parsing has started');
        $setter($parser);
    }

    public function testSettersAllowedAgainAfterNewFile(): void
    {
        $parser = $this->parser(self::USERS);
        self::next($parser);
        $parser->setFile(__DIR__ . '/../../examples/example1.csv');

        $row = self::next($parser->setWhitelist(['user.age']));

        self::assertSame(['user' => ['age' => '37']], $row);
        self::assertSame(1, $parser->rowsFetched);
    }

    public function testSettingFileResetsColumnCount(): void
    {
        $parser = $this->parser("a,b\n1,2\n");
        self::next($parser);

        $parser->setStream($this->stream("a,b,c\n1,2,3\n"));

        self::assertSame(['a' => '1', 'b' => '2', 'c' => '3'], self::next($parser));
    }

    public function testMissingHeaderRow(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing header row');
        self::next($this->parser(''));
    }

    public function testColumnCountMismatch(): void
    {
        $parser = $this->parser("a,b,c\n1,2,3\n1,2\n");
        self::next($parser);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('record 3 has 2 columns, expected 3');
        self::next($parser);
    }

    public function testDuplicateColumnsAreRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column "a.b" conflicts with column "a.b"');
        self::next($this->parser("a.b,a.b\n1,2\n"));
    }

    public function testLeafAndBranchColumnsAreRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column "user.name" conflicts with column "user"');
        self::next($this->parser("user,user.name\n1,2\n"));
    }

    public function testReadingWithoutFileThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No file has been provided');
        self::next(new Parser());
    }

    public function testOpeningFileThatDoesNotExist(): void
    {
        $this->expectException(Exception::class);
        new Parser()->setFile('john_jacob_jingleheimer_schmidt');
    }

    public function testOpeningDirectoryThrows(): void
    {
        $this->expectException(Exception::class);
        new Parser(__DIR__);
    }

    public function testSetStreamRejectsNonResource(): void
    {
        $this->expectException(Exception::class);
        // @phpstan-ignore argument.type
        new Parser()->setStream('not a stream');
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidCharacterOptions(): iterable
    {
        yield 'empty delimiter' => [['delimiter' => '']];
        yield 'long delimiter' => [['delimiter' => '::']];
        yield 'empty enclosure' => [['enclosure' => '']];
        yield 'long escape' => [['escape' => '\\\\']];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidCharacterOptions')]
    public function testCharacterOptionsMustBeSingleCharacters(array $options): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a single character');
        $this->parser(self::USERS, $options);
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unknown option(s): colum_map');
        $this->parser(self::USERS, ['colum_map' => []]);
    }
}
