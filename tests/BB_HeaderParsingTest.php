<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use YAWAF\Core\Http\HeaderParser;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BA_HeaderParsingTest extends TestCase
{
    #[DataProvider('parsingCustomHeadersDataProvider')]
    public function testParsingCustomHeaders($values, $options, $expectedResults)
    {
        $hp = new HeaderParser();
        $this->assertSame($expectedResults, $hp->normalizeCustomHeaderValue($values, $options));
    }

    public static function parsingCustomHeadersDataProvider()
    {
        return [
            // 0 = multi-valued, no double-quotes
            [[], 0, []],
            [[''], 0, []],
            [['hello'], 0, ['hello']],
            [[" \thello \t"], 0, ['hello']],
            [['hello world'], 0, ['hello world']],
            [["hello'world"], 0, ["hello'world"]],

            [['hello,world'], 0, ['hello', 'world']],
            [['hello , world'], 0, ['hello', 'world']],
            [["hello  \t ,\t \tworld"], 0, ['hello', 'world']],
            [["hello,, ,  ,\t,\t\tworld"], 0, ['hello', 'world']],
            [[",hello, world"], 0, ['hello', 'world']],
            [["hello, world,"], 0, ['hello', 'world']],
            [[",hello, world,"], 0, ['hello', 'world']],

            [['hello"world'], 0, ['']],
            [['"hello world"'], 0, ['']],
            [['hello world"'], 0, ['']],
            [['"hello world'], 0, ['']],

            [['hello', 'world'], 0, ['hello', 'world']],
            [[',hello, ,', 'world'], 0, ['hello', 'world']],
            [['hello', ', ,world,'], 0, ['hello', 'world']],
            [['', 'hello,world', ''], 0, ['hello', 'world']],
            [['hello,world', 'again'], 0, ['hello', 'world', 'again']],
            [[',,hello,,world,,' ,'again'], 0, ['hello', 'world', 'again']],

            // 1 = singleton, no double-quotes

            // 2 = multi-valued, double-quotes

            // 3 = singleton, double-quotes
        ];
    }
}
