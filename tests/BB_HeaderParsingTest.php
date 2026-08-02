<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use YAWAF\Core\Http\HeaderFormat;
use YAWAF\Core\Http\HeaderParser;
use YAWAF\Core\Http\HeaderQuotedSpansFormat;
use YAWAF\Core\Http\HeaderSpec;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BB_HeaderParsingTest extends TestCase
{
    #[DataProvider('parsingCustomHeadersDataProvider')]
    public function testParsingCustomHeaders($values, $expectedResults)
    {
        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::Generic)]);
        $this->assertSame($expectedResults, $hp->normalizeHeaderValue('Custom', $values));
    }

    // 1. multi-valued, no double-quoted-strings header
    public static function parsingCustomHeadersDataProvider()
    {
        return [
            [[], []],
            [[''], []],
            [['hello'], ['hello']],
            [[" \thello \t"], ['hello']],
            [['hello world'], ['hello world']],
            [["hello'world"], ["hello'world"]],

            [['hello,world'], ['hello', 'world']],
            [['hello , world'], ['hello', 'world']],
            [["hello  \t ,\t \tworld"], ['hello', 'world']],
            [["hello,, ,  ,\t,\t\tworld"], ['hello', 'world']],
            [[",hello, world"], ['hello', 'world']],
            [["hello, world,"], ['hello', 'world']],
            [[",hello, world,"], ['hello', 'world']],

            [['"'], ['"']],
            [['""'], ['""']],
            [['"""'], ['"""']],
            [[' " " "'], ['" " "']],
            [['hello"world'], ['hello"world']],
            [['hello world"'], ['hello world"']],
            [['"hello world'], ['"hello world']],
            [['"hello world"'], ['"hello world"']],
            [['"hello,world"'], ['"hello', 'world"']],
            [['"hello, world"'], ['"hello', 'world"']],

            [['hello', 'world'], ['hello', 'world']],
            [[',hello, ,', 'world'], ['hello', 'world']],
            [['hello', ', ,world,'], ['hello', 'world']],
            [['', 'hello,world', ''], ['hello', 'world']],
            [['hello,world', 'again'], ['hello', 'world', 'again']],
            [[',,hello,,world,,' ,'again'], ['hello', 'world', 'again']],
        ];
    }

    #[DataProvider('parsingDQHeadersDataProvider')]
    public function testParsingDQHeaders($values, $expectedResults)
    {
        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::Generic, null, HeaderQuotedSpansFormat::QuotedString)]);
        $this->assertSame($expectedResults, $hp->normalizeHeaderValue('Custom', $values, $errors));
    }

    // 2. multi-valued, allows double-quoted-strings header
    public static function parsingDQHeadersDataProvider()
    {
        return [
            [[], []],
            [[''], []],
            [['hello'], ['hello']],
            [[" \thello \t"], ['hello']],
            [['hello world'], ['hello world']],
            [["hello'world"], ["hello'world"]],

            [['hello,world'], ['hello', 'world']],
            [['hello , world'], ['hello', 'world']],
            [["hello  \t ,\t \tworld"], ['hello', 'world']],
            [["hello,, ,  ,\t,\t\tworld"], ['hello', 'world']],
            [[",hello, world"], ['hello', 'world']],
            [["hello, world,"], ['hello', 'world']],
            [[",hello, world,"], ['hello', 'world']],

            // NB: these 3 tests check corner-case situations for which there is no well-defined result.
            // Unlike the Structured Fields RFC, the main HTTP rfcs have no indication about error handling and
            // how to treat headers where the value does not satisfy the specification.
            // Given the and that the same matcher can be used both within an Allow and a Deny rule, it is also hard to
            // make a good choice regarding the default behaviour for when trying to match the content of a non-compliant
            // header. For this reason, a separate matcher has been developed, focused on finding non-compliant headers.
            [['hello"world'], ['helloworld']],
            [['hello world"'], ['hello world']], /// @todo... should ew modify the parse to change this result?
            [['"hello world'], ['hello world']],

            [['""'], ['']],
            [['"\\""'], ['"']],
            [['"hello world"'], ['hello world']],
            [['"hello,world"'], ['hello,world']],
            [['"hello, world"'], ['hello, world']],
            [['"hello\\"world"'], ['hello"world']],
            [['"hello \\world"'], ['hello world']],
            [['"\\h\\e\\l\\l\\o \\w\\o\\r\\l\\d"'], ['hello world']],

            [['hello', 'world'], ['hello', 'world']],
            [[',hello, ,', 'world'], ['hello', 'world']],
            [['hello', ', ,world,'], ['hello', 'world']],
            [['', 'hello,world', ''], ['hello', 'world']],
            [['hello,world', 'again'], ['hello', 'world', 'again']],
            [[',,hello,,world,,', 'again'], ['hello', 'world', 'again']],

            [[' "hello world" '], ['hello world']],
            [['hello "hello,world" world'], ['hello hello,world world']],
            [[' hello  "hello,world"  world'], ['hello  hello,world  world']],
            [['hello "hello,world" world '], ['hello hello,world world']],
            [['hello " hello,world " world '], ['hello  hello,world  world']],
            [['" hello,world "'], [' hello,world ']],

            [['""', 'again'], ['', 'again']],
            [['"\\""', 'again'], ['"', 'again']],
            [['"hello world"', 'again'], ['hello world', 'again']],
            [['"hello,world"', 'again'], ['hello,world', 'again']],
            [['"hello, world"', 'again'], ['hello, world', 'again']],
            [['"hello\\"world"', 'again'], ['hello"world', 'again']],
            [['"hello \\world"', 'again'], ['hello world', 'again']],
            [['"\\h\\e\\l\\l\\o \\w\\o\\r\\l\\d"', 'again'], ['hello world', 'again']],

            [['hello, "hello, world", world'], ['hello', 'hello, world', 'world']],

            /// @todo any more DQ strings to test?
        ];
    }

/// @todo... tests for more cases:
    // singleton, no double-quotes
    // singleton, double-quotes
    // dates
    // cookies
    // different ways of dealing with errors
}
