<?php

declare(strict_types=1);

namespace JMToolkit\Tests\Csv\Mapping;

use JMToolkit\Csv\Column\ColumnMap;
use JMToolkit\Csv\Column\Denylist;
use JMToolkit\Csv\Mapping\ClassHydrator;
use JMToolkit\Csv\Mapping\Compiler;
use JMToolkit\Csv\Mapping\MappingException;
use JMToolkit\Csv\Parser;
use JMToolkit\Csv\Rows;
use JMToolkit\Tests\Csv\Mapping\Fixture\AbstractThing;
use JMToolkit\Tests\Csv\Mapping\Fixture\Address;
use JMToolkit\Tests\Csv\Mapping\Fixture\Color;
use JMToolkit\Tests\Csv\Mapping\Fixture\Defaults;
use JMToolkit\Tests\Csv\Mapping\Fixture\ListOfString;
use JMToolkit\Tests\Csv\Mapping\Fixture\ListOfUnknown;
use JMToolkit\Tests\Csv\Mapping\Fixture\Name;
use JMToolkit\Tests\Csv\Mapping\Fixture\NeedsInt;
use JMToolkit\Tests\Csv\Mapping\Fixture\NeedsName;
use JMToolkit\Tests\Csv\Mapping\Fixture\NeedsStatus;
use JMToolkit\Tests\Csv\Mapping\Fixture\Priority;
use JMToolkit\Tests\Csv\Mapping\Fixture\Scalars;
use JMToolkit\Tests\Csv\Mapping\Fixture\Status;
use JMToolkit\Tests\Csv\Mapping\Fixture\Tag;
use JMToolkit\Tests\Csv\Mapping\Fixture\UnionType;
use JMToolkit\Tests\Csv\Mapping\Fixture\User;
use JMToolkit\Tests\Csv\Mapping\Fixture\Variadic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rows::class)]
#[CoversClass(ClassHydrator::class)]
#[CoversClass(Compiler::class)]
#[CoversClass(MappingException::class)]
final class MappingTest extends TestCase
{
    private const string USERS = "id,name.first,name.last,email.work,email.home,address.street,address.city,address.zip,tags.0.label,tags.1.label,status,joined\n"
        . "1,James,Moran,james@work.example,james@home.example,1 Main St,Springfield,12345,admin,staff,active,2011-09-28\n"
        . "2,Jane,Doe,jane@work.example,,,,,staff,,inactive,\n";

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

    private function rows(string $csv, Parser $parser = new Parser()): Rows
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $csv);
        rewind($stream);
        $this->streams[] = $stream;

        return $parser->parseStream($stream);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function map(string $csv, string $class, Parser $parser = new Parser()): array
    {
        return array_values(iterator_to_array($this->rows($csv, $parser)->into($class)));
    }

    public function testMapsRowsOntoClasses(): void
    {
        [$james, $jane] = $this->map(self::USERS, User::class);

        self::assertEquals(
            new User(
                id: 1,
                name: new Name('James', 'Moran'),
                email: 'james@work.example',
                address: new Address('1 Main St', 'Springfield', '12345'),
                tags: [new Tag('admin'), new Tag('staff')],
                status: Status::Active,
                joined: new \DateTimeImmutable('2011-09-28'),
            ),
            $james,
        );

        // Empty cells: a nullable object and date become null, empty list items are left out.
        self::assertNull($jane->address);
        self::assertNull($jane->joined);
        self::assertEquals([new Tag('staff')], $jane->tags);
        self::assertSame(Status::Inactive, $jane->status);
        // Missing column: the default is used.
        self::assertTrue($jane->active);
    }

    public function testConvertsValuesToParameterTypes(): void
    {
        [$row] = $this->map(
            "int,float,bool,string,nullableInt,nullableString,mixed,untyped,priority,color,date,mutableDate,scores.0,scores.1,scores.2,raw.a,raw.b.c\n"
            . "-42,1.5,yes,text,,,m,u,2,Green,2020-01-02 03:04:05,2021-06-07,7,,9,x,y\n",
            Scalars::class,
        );

        self::assertSame(-42, $row->int);
        self::assertSame(1.5, $row->float);
        self::assertTrue($row->bool);
        self::assertSame('text', $row->string);
        self::assertNull($row->nullableInt);
        self::assertNull($row->nullableString);
        self::assertSame('m', $row->mixed);
        self::assertSame('u', $row->untyped);
        self::assertSame(Priority::High, $row->priority);
        self::assertSame(Color::Green, $row->color);
        self::assertInstanceOf(\DateTimeImmutable::class, $row->date);
        self::assertSame('2020-01-02 03:04:05', $row->date->format('Y-m-d H:i:s'));
        self::assertInstanceOf(\DateTime::class, $row->mutableDate);
        self::assertSame([7, 9], $row->scores);
        self::assertSame(['a' => 'x', 'b' => ['c' => 'y']], $row->raw);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function booleans(): iterable
    {
        foreach (['1', 'true', 'TRUE', 'yes', 'on'] as $value) {
            yield $value => [$value, true];
        }
        foreach (['0', 'false', 'no', 'off', ''] as $value) {
            yield "'{$value}'" => [$value, false];
        }
    }

    #[DataProvider('booleans')]
    public function testBooleans(string $value, bool $expected): void
    {
        $csv = "int,float,bool,string,nullableInt,nullableString,mixed,untyped,priority,color,date,mutableDate,scores.0,raw.a\n"
            . "1,1,{$value},s,,,m,u,1,Red,2020-01-01,2020-01-01,1,r\n";

        self::assertSame($expected, $this->map($csv, Scalars::class)[0]->bool);
    }

    /**
     * @return iterable<string, array{string, Defaults}>
     */
    public static function defaults(): iterable
    {
        yield 'all columns' => ["first,middle,last\na,1,c\n", new Defaults('a', 1, 'c')];
        yield 'trailing defaults' => ["first\na\n", new Defaults('a')];
        yield 'default before a column' => ["first,last\na,c\n", new Defaults('a', last: 'c')];
    }

    #[DataProvider('defaults')]
    public function testDefaultsAreUsedForMissingColumns(string $csv, Defaults $expected): void
    {
        self::assertEquals([$expected], $this->map($csv, Defaults::class));
    }

    public function testColumnAttributeIsMatchedAfterColumnMap(): void
    {
        $parser = new Parser(columnMap: new ColumnMap()->rename('work_email', 'email.work'));
        $csv = "id,name.first,name.last,work_email,tags.0.label,status,joined\n1,A,B,a@example.com,,active,\n";

        $user = $this->map($csv, User::class, $parser)[0];

        self::assertSame('a@example.com', $user->email);
        self::assertNull($user->address);
        self::assertSame([], $user->tags);
    }

    public function testKeysAreRowNumbersAndIterationContinues(): void
    {
        $rows = $this->rows(self::USERS);
        foreach ($rows as $row) {
            break;
        }

        $users = iterator_to_array($rows->into(User::class));

        self::assertSame([1], array_keys($users));
        self::assertSame(2, $users[1]->id);
        self::assertSame(2, $rows->rowsFetched);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'int' => ['abc', 'Row 2, column "value": "abc" is not a valid int for ' . NeedsInt::class . '::$value'];
        yield 'float in an int' => ['1.5', 'Row 2, column "value": "1.5" is not a valid int'];
        yield 'empty int' => ['', 'Row 2, column "value": "" is not a valid int'];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueReportsRowAndColumn(string $value, string $message): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage($message);
        // Enclosed, so an empty value is an empty cell rather than a blank line.
        $this->map("value\n1\n\"{$value}\"\n", NeedsInt::class);
    }

    public function testInvalidEnumValue(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('Row 1, column "value": "unknown" is not a valid ' . Status::class);
        $this->map("value\nunknown\n", NeedsStatus::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidOtherValues(): iterable
    {
        yield 'bool' => ['maybe'];
        yield 'int-backed enum' => ['3'];
        yield 'pure enum' => ['Blue'];
        yield 'date' => ['not a date'];
        yield 'empty date' => [''];
    }

    #[DataProvider('invalidOtherValues')]
    public function testInvalidValuesOfOtherTypes(string $value): void
    {
        $values = ['bool' => '1', 'priority' => '1', 'color' => 'Red', 'date' => '2020-01-01'];
        $column = match ($value) {
            'maybe' => 'bool',
            '3' => 'priority',
            'Blue' => 'color',
            default => 'date',
        };
        $values[$column] = $value;
        $csv = "int,float,bool,string,nullableInt,nullableString,mixed,untyped,priority,color,date,mutableDate,scores.0,raw.a\n"
            . "1,1,{$values['bool']},s,,,m,u,{$values['priority']},{$values['color']},{$values['date']},2020-01-01,1,r\n";

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(sprintf('Row 1, column "%s": "%s" is not a valid', $column, $value));
        $this->map($csv, Scalars::class);
    }

    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function classesThatDoNotFit(): iterable
    {
        yield 'missing required column' => ["other\n1\n", NeedsInt::class, NeedsInt::class . '::$value needs column "value", which is not in the file'];
        yield 'missing nested column' => ["name.first\nA\n", NeedsName::class, Name::class . '::$last needs column "name.last", which is not in the file'];
        yield 'union type' => ["value\n1\n", UnionType::class, UnionType::class . '::$value has a union or intersection type'];
        yield 'variadic' => ["values\n1\n", Variadic::class, Variadic::class . '::$values is variadic'];
        yield 'abstract class' => ["value\n1\n", AbstractThing::class, AbstractThing::class . ' cannot be instantiated'];
        yield 'class from one cell' => ["name\nA\n", NeedsName::class, 'Column "name" holds a single value, but ' . NeedsName::class . '::$name is ' . Name::class];
        yield 'scalar from nested cells' => ["value.a\n1\n", NeedsInt::class, 'Column "value" has nested columns, but ' . NeedsInt::class . '::$value is int'];
        yield 'enum from nested cells' => ["value.a\n1\n", NeedsStatus::class, 'Column "value" has nested columns, but ' . NeedsStatus::class . '::$value is ' . Status::class];
        yield 'list of on a non-array' => ["value.0\n1\n", ListOfString::class, ListOfString::class . '::$value has #[ListOf], so it must be typed array'];
        yield 'list without numbered columns' => ["value.a\n1\n", ListOfUnknown::class, 'so column "value" must have numbered columns such as "value.0"'];
        yield 'list of an unknown type' => ["value.0\n1\n", ListOfUnknown::class, "#[ListOf('Nope\\Missing')], which is not a class, enum or scalar type"];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('classesThatDoNotFit')]
    public function testClassThatDoesNotFitTheFileFailsBeforeAnyRow(string $csv, string $class, string $message): void
    {
        $rows = $this->rows($csv);

        try {
            iterator_to_array($rows->into($class));
            self::fail('Expected a MappingException');
        } catch (MappingException $e) {
            self::assertStringContainsString($message, $e->getMessage());
            self::assertStringNotContainsString('Row ', $e->getMessage());
        }

        self::assertSame(0, $rows->rowsFetched);
    }

    public function testFilteredColumnCountsAsMissing(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('needs column "value", which is not in the file');
        $this->map("value\n1\n", NeedsInt::class, new Parser(filter: new Denylist('value')));
    }
}
