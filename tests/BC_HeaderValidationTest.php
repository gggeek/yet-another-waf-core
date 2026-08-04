<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use YAWAF\Core\Http\HeaderFormat;
use YAWAF\Core\Http\HeaderParser;
use YAWAF\Core\Http\HeaderQuotedSpansFormat;
use YAWAF\Core\Http\HeaderSpec;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class BC_HeaderValidationTest extends TestCase
{
    #[DataProvider('compliantHeadersDataProvider')]
    public function testCompliantHeaders(array $values, string $format)
    {
        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::from($format))]);
        $this->assertTrue($hp->validateHeaderValue('Custom', $values));
    }

    public static function compliantHeadersDataProvider()
    {
        return [
            [['hello,world'], 'generic'],
            [['hello,world','again'], 'generic'],

            [['hello=world'], 'cookie'],
            [['hello="world"'], 'cookie'],
            [['hello=world; world=hello'], 'cookie'],
            [['hello=world; world="hello"'], 'cookie'],

            [['Sun, 06 Nov 1994 08:49:37 GMT'], 'date'],
            [['Sunday, 06-Nov-94 08:49:37 GMT'], 'date'],
            [['Sun Nov  6 08:49:37 1994'], 'date'],

            [['1'], 'integer'],

            [['{ "hello" : "world", "array":[true, false, null, [], {}, ["nested"]]}'], 'json'],

            [['hello,world'], 'token'],
            [['hello,world','again'], 'token'],
        ];
    }

    #[DataProvider('nonCompliantHeadersDataProvider')]
    public function testNonCompliantHeaders(array $values, string $format)
    {
        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::from($format))]);
        $this->assertFalse($hp->validateHeaderValue('Custom', $values));
    }

    public static function nonCompliantHeadersDataProvider()
    {
        return [
            [['hello =world'], 'cookie'],
            [['hello= world'], 'cookie'],
            [['hello="world'], 'cookie'],
            [['hello=world"'], 'cookie'],
            [['hello=world;'], 'cookie'],
            [['hello=world ;yo=lo'], 'cookie'],
            [['hello=world ; yo=lo'], 'cookie'],
            [["hello=world;  yo=lo"], 'cookie'],
            [["hello=world;\tyo=lo"], 'cookie'],

            [['S.n, 06 Nov 1994 08:49:37 GMT'], 'date'],
            [['S.nday, 06-Nov-94 08:49:37 GMT'], 'date'],
            [['S.n Nov  6 08:49:37 1994'], 'date'],

            [['"hello,world"'], 'integer'],

            [['{"hello,world"'], 'json'],

            [['"hello,world"'], 'token'],
        ];
    }

    public function testSingletonHeaders()
    {
        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::from('generic'), null, HeaderQuotedSpansFormat::None, true)]);
        $this->assertFalse($hp->validateHeaderValue('Custom', ['hello,world']));
        $this->assertFalse($hp->validateHeaderValue('Custom', ['"hello,world"']));
        $this->assertFalse($hp->validateHeaderValue('Custom', ['hello','world']));
        $this->assertFalse($hp->validateHeaderValue('Custom', ['"hello','world"']));

        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::from('generic'), null, HeaderQuotedSpansFormat::QuotedString, true)]);
        $this->assertTrue($hp->validateHeaderValue('Custom', ['"hello,world"']));
        $this->assertTrue($hp->validateHeaderValue('Custom', ['hello","world']));

        $hp = new HeaderParser(['Custom' => new HeaderSpec(HeaderFormat::from('generic'), null, HeaderQuotedSpansFormat::QuotedString, true)]);
        $this->assertFalse($hp->validateHeaderValue('Custom', ['hello",world"','world']));
        $this->assertFalse($hp->validateHeaderValue('Custom', ['hello"\\,world"','world']));
    }
}
