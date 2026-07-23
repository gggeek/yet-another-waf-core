<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the ServerRequestCreator class for all kind of weird http input.
 * In fact these tests are more of a smoke-test for the webserver used to run PHP, how it handles malformed http requests,
 * and what it lets through to the application.
 *
 * @todo... more tests: - custom http methods (incl. full "token" production)
 *                      - anomalies in the start line
 *                      - unexpected values for Host header (incl. double Host)
 *                      - header with a value continued on the next line (check rfc9110: are those still supported or should they be dropped?)
 */
class BA_ServerRequestCreatorTest extends ServerTestCase
{
    /**
     * Test http headers which cause all (tested) servers to pass them on to PHP - single header
     */
    #[DataProvider('singletonHttpHeaderDataProvider')]
    public function testSingletonHttpHeader(string $headers, string $expectedHeaderName, $expectedHeaderValue,
        string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $failureMessage = $this->getRespDetails($response);
        $data = $this->getDecodedBody($response);
        $headers = $data['serverRequest']['headers'];
        $this->assertArrayHasKey($expectedHeaderName, $headers, $failureMessage);
        $this->assertSame($expectedHeaderValue, $headers[$expectedHeaderName][0], $failureMessage);
    }

    /**
     * @see https://developers.cloudflare.com/rules/transform/request-header-modification/reference/header-format/
     * @see https://community.f5.com/kb/security-insights/f5-nginx-http-request-header-rules-what%E2%80%99s-permitted-and-what%E2%80%99s-not/334564
     */
    public static function singletonHttpHeaderDataProvider(): array
    {
        $cases = [
            // vanilla
            ['Custom: hey', 'Custom', 'hey'],
            // making sure 0, null and false do not gt dropped/interpreted
            ['C: 0', 'C', '0'],
            ['Custom: null', 'Custom', 'null'],
            ['Custom: false', 'Custom', 'false'],
            // a header with the shortest possible purely numeric name
            ['0: 1', '0', '1'],
            // OWS around value
            ["Custom: \t \t hey\t \t \t", 'Custom', 'hey'],
            // casing of rebuilt header name, whitespace inside value
            ["custom: hey hey\they", 'Custom', "hey hey\they"],
            // no interpretation of quoted-string by default
            ['CuStOm: "hey hey"', 'Custom', '"hey hey"'],
            // rfc9110 "token" production - for value
            ['Custom: !#$%&\'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 'Custom', '!#$%&\'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'],
            // the chars not allowed in rfc9110 "token" - for value
            ['Custom: (),/:;<=>?@[\\]{}', 'Custom', '(),/:;<=>?@[\\]{}'],

            // DIGIT / ALPHA / "-" - for name
            ['0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-: hey', '0123456789abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz-', 'hey'],
        ];

/// @todo... test: chars above 127 (aka. 'obs-text') in header value. Atm this test fails because of json-encode server-side
///          expecting valid utf8
        //$obsText = '';
        //for ($i = 128; $i < 256; $i++) {
        //    $obsText .= chr($i);
        //}
        //$cases[] = ['Custom: ' . $obsText, 'Custom', $obsText];

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getCommonDataProviderOptions() as $options) {
                $out[] = $line + $options;
            }
        }
        return $out;
    }

    /**
     * Test http headers which cause all (tested) servers to pass them on to PHP - duplicate header
     */
    #[DataProvider('duplicateHttpHeaderDataProvider')]
    public function testDuplicateHttpHeader(string $headers, string $expectedHeaderName, $expectedHeaderValue,
        string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $failureMessage = $this->getRespDetails($response);
        $data = $this->getDecodedBody($response);
        $headers = $data['serverRequest']['headers'];
        $this->assertArrayHasKey($expectedHeaderName, $headers, $failureMessage);
        $this->assertSame($expectedHeaderValue, $headers[$expectedHeaderName][0], $failureMessage);
    }

    public static function duplicateHttpHeaderDataProvider(): array
    {
        $cases = [
            // vanilla
            ["Custom: hey\r\nCustom: there", 'Custom', 'hey, there'],
            // OWS
            ["Custom: 1 \r\nCustom:2\r\nCustom: 3 ", 'Custom', '1, 2, 3'],
/// @todo... add a mix of double-quoted strings
        ];

        if ($_ENV['SERVER_TYPE'] !== 'nginx') {
            /// @todo... this test is fun: frankenphp and apache agree on it, whereas nginx does not strip the tabs from whitespace !!
            ///          See https://github.com/nginx/nginx/issues/1597 -> https://github.com/nginx/nginx/issues/187
            $cases[] = ["Custom: \t1\t\r\nCustom: 2 \r\nCustom: \t3\t", 'Custom', '1, 2, 3'];
        }

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getCommonDataProviderOptions() as $options) {
                $out[] = $line + $options;
            }
        }
        return $out;
    }

    /**
     * Test http headers which cause all (tested) servers to either drop them or return a 400 error
     */
    #[DataProvider('droppedHttpHeaderDataProvider')]
    public function testDroppedHttpHeader(string $headers, bool $allow404s = true, string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $failureMessage = $this->getRespDetails($response);
        // Different webservers react differently to this test - some drop the header, some reject the request.
        // Allow the test data to specify if 404s should be acceptable
        if ($allow404s && preg_match('#^HTTP/1.(0|1) 400 #', $response)) {
            $this->assertEquals(1, 1);
            return;
        }
        $data = $this->getDecodedBody($response);
        $headers = $data['serverRequest']['headers'];
        $this->assertArrayHasKey('Host', $headers, $failureMessage);
        $this->assertCount(1, $headers, $failureMessage);
    }

    public static function droppedHttpHeaderDataProvider(): array
    {
        $cases = [
            ['Custom:', false],

            /// @todo figure out why this one does get dropped or refused by all servers
            ['Cus_tom: hey', false],

            // (),/:;<=>?@[\\]{}
            ['Cus(tom: hey', true],
            ['Cus)tom: hey', true],
            ['Cus,tom: hey', true],
            ['Cus/tom: hey', true],
/// @todo... add a different test for this
            //['Cus:tom: hey', true],
            ['Cus;tom: hey', true],
            ['Cus<tom: hey', true],
            ['Cus=tom: hey', true],
            ['Cus>tom: hey', true],
            ['Cus?tom: hey', true],
            ['Cus@tom: hey', true],
            ['Cus[tom: hey', true],
            ['Cus]tom: hey', true],
            ['Cus\\tom: hey', true],
            ['Cus{tom: hey', true],
            ['Cus}tom: hey', true],
        ];

        // NB: FrankenPHP, as of 2026/7/21 at least, _does_ allow these chars in header names !!
        if ($_ENV['SERVER_TYPE'] !== 'frankenphp') {
            $cases = $cases + [
                // !#$%&\'*+-.^_`|~
                ['Cus!tom: hey', true],
                ['Cus#tom: hey', true],
                ['Cus$tom: hey', true],
                ['Cus%tom: hey', true],
                ['Cus&tom: hey', true],
                ['Cus\'tom: hey', true],
                ['Cus*tom: hey', true],
                ['Cus+tom: hey', true],
                ['Cus.tom: hey', true],
                ['Cus^tom: hey', true],
                ['Cus`tom: hey', true],
                ['Cus|tom: hey', true],
                ['Cus~tom: hey', true],
            ];
        }

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getCommonDataProviderOptions() as $options) {
                $out[] = $line + $options;
            }
        }
        return $out;
    }

    /**
     * Test http headers which cause all (tested) servers to return a 400 error
     */
    #[DataProvider('rejectedHttpHeaderDataProvider')]
    public function testRejectedHttpHeader(string $headers, string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $failureMessage = $this->getRespDetails($response);
        $this->assertMatchesRegularExpression('#^HTTP/1.(0|1) 400 #', $response, $failureMessage);
    }

    public static function rejectedHttpHeaderDataProvider(): array
    {
        $cases = [
            [':'],
            ['Custom'],
            // whitespace in header name
            ['Cus tom : hey'],
            ['Custom : hey'],
            [' Custom: hey'],
            ["Custom\t: hey"],
            ["\tCustom: hey"],
            // non-ascii char in header name
            ["Cüstom: hey"],
            // ctrl chars in header name
            ["Custom" . chr(0) . ": hey"],
            ["Custom" . chr(1) . ": hey"],
            ["Custom" . chr(2) . ": hey"],
            ["Custom" . chr(3) . ": hey"],
            ["Custom" . chr(4) . ": hey"],
            ["Custom" . chr(5) . ": hey"],
            ["Custom" . chr(6) . ": hey"],
            ["Custom" . chr(7) . ": hey"],
            ["Custom" . chr(8) . ": hey"],
            ["Custom" . chr(11) . ": hey"],
            ["Custom" . chr(12) . ": hey"],
            ["Custom" . chr(14) . ": hey"],
            ["Custom" . chr(15) . ": hey"],
            ["Custom" . chr(16) . ": hey"],
            ["Custom" . chr(17) . ": hey"],
            ["Custom" . chr(18) . ": hey"],
            ["Custom" . chr(19) . ": hey"],
            ["Custom" . chr(20) . ": hey"],
            ["Custom" . chr(21) . ": hey"],
            ["Custom" . chr(22) . ": hey"],
            ["Custom" . chr(23) . ": hey"],
            ["Custom" . chr(24) . ": hey"],
            ["Custom" . chr(25) . ": hey"],
            ["Custom" . chr(26) . ": hey"],
            ["Custom" . chr(27) . ": hey"],
            ["Custom" . chr(28) . ": hey"],
            ["Custom" . chr(29) . ": hey"],
            ["Custom" . chr(30) . ": hey"],
            ["Custom" . chr(31) . ": hey"],
            ["Custom" . chr(127) . ": hey"], // DEL
            // ctrl chars in header value
            ["Custom: " . chr(0)],
            ["Custom: " . chr(1)],
            ["Custom: " . chr(2)],
            ["Custom: " . chr(3)],
            ["Custom: " . chr(4)],
            ["Custom: " . chr(5)],
            ["Custom: " . chr(6)],
            ["Custom: " . chr(7)],
            ["Custom: " . chr(8)],
            ["Custom: " . chr(11)],
            ["Custom: " . chr(12)],
            ["Custom: " . chr(14)],
            ["Custom: " . chr(15)],
            ["Custom: " . chr(16)],
            ["Custom: " . chr(17)],
            ["Custom: " . chr(18)],
            ["Custom: " . chr(19)],
            ["Custom: " . chr(20)],
            ["Custom: " . chr(21)],
            ["Custom: " . chr(22)],
            ["Custom: " . chr(23)],
            ["Custom: " . chr(24)],
            ["Custom: " . chr(25)],
            ["Custom: " . chr(26)],
            ["Custom: " . chr(27)],
            ["Custom: " . chr(28)],
            ["Custom: " . chr(29)],
            ["Custom: " . chr(30)],
            ["Custom: " . chr(31)],
            ["Custom: " . chr(127)], // DEL

            /// @todo are there more known _always unsupported_ chars (ie. triggering a 4xx/5xx) in header name, header value?
        ];

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getCommonDataProviderOptions() as $options) {
                $out[] = $line + $options;
            }
        }
        return $out;
    }

    protected static function getCommonDataProviderOptions(): array
    {
        $out = [];
        foreach (self::getSupportedServerSchemes() as $serverScheme) {
            foreach (['1.0', '1.1'] as $protocolVersion) {
                $out[] = [$protocolVersion, $serverScheme];
            }
        }
        return $out;
    }

    protected function customHeadersRequest(string $headers, string $method = 'GET', string $httpVersion = '1.0',
        string $serverScheme = 'http'): string
    {
        $client = $this->getSimpleClient([], ['server_scheme' => $serverScheme]);

        $baseUri = $this->getServerBaseUri();
        $targetAddress = $this->getServerAddress();

        $payload = "$method " . $this->getServerPath() . " HTTP/$httpVersion\r\n" .
            'Host: ' . preg_replace('#^https?://#', '', $baseUri) . "\r\n" . $headers . "\r\n";
        if ($httpVersion === '1.1') {
            // avoid timeouts
            $payload .= "Connection: close\r\n";
        }
        $payload .= "\r\n";

        return $client->sendPayload($targetAddress, $payload);
    }

    protected function getServerAddress(): string
    {
        /// @todo resolve other hostnames besides localhost
        $targetAddress = str_replace('://localhost', '://127.0.0.1', $this->getServerBaseUri());
        if (str_starts_with($targetAddress, 'http://')) {
            $targetAddress = preg_replace('#^http://#', 'tcp://', $targetAddress);
            if (!preg_match('#:[0-9]+$#', $targetAddress)) {
                $targetAddress .= ':80';
            }
        } elseif(str_starts_with($targetAddress, 'https://')) {
            $targetAddress = preg_replace('#^https://#', 'tls://', $targetAddress);
            if (!preg_match('#:[0-9]+$#', $targetAddress)) {
                $targetAddress .= ':443';
            }
        } else {
            throw new \RuntimeException("Unsupported target address protocol: $targetAddress");
        }
        return $targetAddress;
    }

    // the API of this function is less than  ideal, but it tries to be compatible with parent::getClient
    protected function getSimpleClient(array $clientOptions = [], array $testOptions = []): SimpleHttpClient
    {
        if (@$testOptions['server_scheme'] === 'unix') {
            $clientOptions['bindto'] = $_ENV['HTTPSERVER_SOCKET'];
        }

        // avoid tests lasting too long in case of things going south - the test server is supposed to respond quickly in any case
        $clientOptions = $clientOptions + [
            'timeout' => 2, // seconds
        ];

        return new SimpleHttpClient($clientOptions);
    }

    protected function getDecodedBody(string $response, $retCode = '200'): array
    {
        /// @todo "In the interest of robustness, a server that is expecting to receive and parse a request-line SHOULD ignore at least one empty line (CRLF) received prior to the request-line"
        $this->assertMatchesRegularExpression('#^HTTP/1.(0|1) ' . preg_quote($retCode, '#') . ' #', $response);
        $body = $this->extractBody($response);
        $data = @json_decode($body, true);
        // support application/php-serialized+base64
        if (json_last_error() !== 0) {
            $data = @base64_decode($data);
            if ($data !== false) {
                $data = unserialize($data, ['allowed_classes' => false]);
            }
        }
        $this->assertIsArray($data);
        return $data;
    }

    /**
     * Really simple separator of body from headers
     */
    protected function extractBody(string $response): string
    {
        /// @todo accept single \n as line terminators: "Although the line terminator for the start-line and fields is
        ///        the sequence CRLF, a recipient MAY recognize a single LF as a line terminator and ignore any preceding CR"
        $pos = strpos($response, "\r\n\r\n");
        if ($pos !== false) {
            return substr($response, $pos + 4);
        }
        return '';
    }

    protected function getRespDetails(string $response): string
    {
        return "Server response:\n$response\n";
    }
}
