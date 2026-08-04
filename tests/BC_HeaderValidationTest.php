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
            [['0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ!#$%&\'*+-.^_`|~abcdefghijklmnopqrstuvwxyz=world'], 'cookie'],
            [['hello=!#$%&\'*+-.^_`|~'], 'cookie'],

            [['Sun, 06 Nov 1994 08:49:37 GMT'], 'date'],
            [['Sunday, 06-Nov-94 08:49:37 GMT'], 'date'],
            [['Sun Nov  6 08:49:37 1994'], 'date'],

            [['1'], 'integer'],

            [['{ "hello" : "world", "array":[true, false, null, [], {}, ["nested"]]}'], 'json'],

            [['hello,world'], 'token'],
            [['hello,world','again'], 'token'],

            // Integer
            [['-1'], 'Item'],
            [['1'], 'Item'],
            [['1234567890'], 'IntegerItem'],
            [['1234567890.0123456789'], 'Item'],
            [['-1234567890.0123456789'], 'DecimalItem'],
            // String
            [['"hello, world"'], 'Item'],
            [['"hello; world"'], 'StringItem'],
            // Base64
            [[':' . base64_encode('hello') . ':'], 'Item'],
            [[':' . base64_encode('') . ':'], 'ByteSequenceItem'],
            // Bool
            [['?0'], 'Item'],
            [['?1'], 'BooleanItem'],
            // Date
            [['@1'], 'Item'],
            [['@123456789'], 'DateItem'],
            // DisplayString
            [['%""'], 'Item'],
            [['%"Hello"'], 'DisplayStringItem'],
            [['%"Hello%20"'], 'Item'],
            [['%"Hello%ff"'], 'Item'],
            [['%"%20Hello"'], 'Item'],
            [['%"%ffHello"'], 'Item'],
            // Token
            [['hello'], 'Item'],
            [['*'], 'TokenItem'],
            [['hello', 'world'], 'Item'], /// @todo arguable! According to the spec, it could fail
            // Parameters
            [['hello;p1'], 'Item'],
            [['hello;p1;p2'], 'Item'],
            [['hello;p1=?0'], 'Item'],
            [['hello;p1=?0;p2=?1'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello"'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello; world"'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello; world";p7=@1'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello; world";p7=@1;p8=%"world"'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello; world";p7=@1;p8=%"world";p9=hello'], 'Item'],
            [['hello;p1=?0;p2=?1;p3=-1;p4=1;p5=1.0;p6="hello; world";p7=@1;p8=%"world";p9=hello;p10=*'], 'Item'],
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
            /// @todo att tests for chars not valid in either cookie name or value

            [['S.n, 06 Nov 1994 08:49:37 GMT'], 'date'],
            [['S.nday, 06-Nov-94 08:49:37 GMT'], 'date'],
            [['S.n Nov  6 08:49:37 1994'], 'date'],

            [['"hello,world"'], 'integer'],

            [['{"hello,world"'], 'json'],

            [['"hello,world"'], 'token'],

            [['hello, world'], 'Item'],
            [['1'], 'BooleanItem'],
            [['?0'], 'StringItem'],
/// @todo...
            //[['hello;'], 'Item'],


            [['-'], 'Item'],
            [['-a'], 'Item'],

            [['"'], 'Item'],
            [['"a'], 'Item'],

            [[':'], 'Item'],
            [[':.:'], 'Item'],

            [['?'], 'Item'],
            [['?a'], 'Item'],

            [['@'], 'Item'],
            [['@a'], 'Item'],

            [['%'], 'Item'],
            [['%"'], 'Item'],
            [['%"a'], 'Item'],
            [['%"a%"'], 'Item'],
            [['%"a%ZZ"'], 'Item'],

            [['a"'], 'Item'],

            [['a;b;;'], 'Item'],
            [['a;b="'], 'Item'],
            [['a;b;c=@'], 'Item'],
            [['a;b=b;c=:'], 'Item'],
            [['a;b=?;c'], 'Item'],
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
