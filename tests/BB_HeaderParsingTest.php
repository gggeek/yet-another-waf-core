<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use YAWAF\Core\Http\HeaderParser;
use YAWAF\Core\Http\HeaderParserOnError;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BB_HeaderParsingTest extends TestCase
{
    #[DataProvider('parsingCustomHeadersDataProvider')]
    public function testParsingCustomHeaders($values, $expectedResults)
    {
        $hp = new HeaderParser(['Custom' => 0]);
        $this->assertSame($expectedResults, $hp->normalizeHeaderValue('Custom', $values));
    }

    // 1. multi-valued, no double-quotes header
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
        $hp = new HeaderParser(['Custom' => HeaderParser::ALLOWS_QUOTED_STRINGS]);
        $this->assertSame($expectedResults, $hp->normalizeHeaderValue('Custom', $values, HeaderParserOnError::ReturnNull));
    }

    // 2. multi-valued, allows double-quotes header
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

            [['hello"world'], ['']],
            [['hello world"'], ['']],
            [['"hello world'], ['']],

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
}
