<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the ServerRequestCreator class for all kind of weird http input
 * @todo... more tests: - custom http methods
 *                      - anomalies in the start line
 *                      - unexpected values for Host header (incl. double Host)
 *                      - a header without ':', etc...
 */
class BA_ServerRequestCreatorTest extends ServerTestCase
{
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
            ['C: 0', 'C', '0'],
            ['0: 1', '0', '1'],
            ['Custom: hey', 'Custom', 'hey'],
            // OWS around value
            ["Custom: \t \t hey\t \t \t", 'Custom', 'hey'],
            // casing of rebuilt header name
            ['custom: hey hey', 'Custom', 'hey hey'],
            // no interpretation of quoted-string by default
            ['CuStOm: "hey hey"', 'Custom', '"hey hey"'],
            // "token" production - for value
            ['Custom: !#$%&\'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 'Custom', '!#$%&\'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'],
            // chars not allowed in "token" - for value
            ['Custom: (),/:;<=>?@[\\]{}', 'Custom', '(),/:;<=>?@[\\]{}'],
            ['0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-: hey', '0123456789abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz-', 'hey'],

/// @todo... test: chars above 127 in header name, header value
        ];

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getCommonDataProviderOptions() as $options) {
                $out[] = $line + $options;
            }
        }
        return $out;
    }

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

        if ($_ENV['SERVER_TYPE'] !== 'frankenphp') {
            // NB: FrankenPHP, as of 2026/7/21, _does_ allow these chars in header names !!
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

    #[DataProvider('rejectedHttpHeaderDataProvider')]
    public function testRejectedHttpHeader(string $headers, string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $failureMessage = $this->getRespDetails($response);
        $this->assertMatchesRegularExpression('#^HTTP/1.(0|1) 400 #', $response);
    }

    public static function rejectedHttpHeaderDataProvider(): array
    {
        $cases = [
            ['Custom : hey'],
            [' Custom: hey'],
            ["Custom\t: hey"],
            ["\tCustom: hey"],
/// @todo... are there more known _always unsupported_ chars (ie. triggering a 404) in header name, header value?
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
        $this->assertMatchesRegularExpression('#^HTTP/1.(0|1) ' . preg_quote($retCode, '#') . ' #', $response);
        $body = $this->extractBody($response);
        $data = json_decode($body, true);
        $this->assertIsArray($data);
        return $data;
    }

    /**
     * Really simple separator of body from headers
     */
    protected function extractBody(string $response): string
    {
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
