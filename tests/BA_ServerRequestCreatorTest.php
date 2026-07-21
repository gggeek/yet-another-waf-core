<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the ServerRequestCreator class for all kind of weird http input
 * @todo... more tests: - custom http methods
 *                      - anomalies in the start line
 *                      - unexpected values for Host header (incl. double Host)
 *                      - a header without ':', with spaces before the ':', etc...
 */
class BA_ServerRequestCreatorTest extends ServerTestCase
{
    #[DataProvider('singletonHTPPHeaderDataProvider')]
    public function testSingletonHTPPHeader(string $headers, string $expectedHeaderName, $expectedHeaderValue,
        string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $data = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $data = $this->getDecodedBody($data);
        $headers = $data['serverRequest']['headers'];
        $this->assertArrayHasKey($expectedHeaderName, $headers);
        $this->assertSame($expectedHeaderValue, $headers[$expectedHeaderName][0]);
    }

    public static function singletonHTPPHeaderDataProvider(): array
    {
        $cases = [
            ['Custom: hey', 'Custom', 'hey'],
            //['Custom : hey', 'Custom', 'hey'],
            ["Custom: \t \t hey\t \t \t", 'Custom', 'hey'],
            ['custom: hey hey', 'Custom', 'hey hey'],
            ['CuStOm: "hey hey"', 'Custom', '"hey hey"'],

            // This one leads to no header being created on the server
            //['Custom:', 'Custom', ''],

/// @todo... test: all supported/unsupported chars in header name, header value
        ];

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getSupportedServerSchemes() as $serverScheme) {
                foreach (['1.0', '1.1'] as $protocolversion) {
                    $out[] = $line + [$protocolversion, $serverScheme];
                }
            }
        }
        return $out;
    }

    #[DataProvider('rejectedHTPPHeaderDataProvider')]
    public function testRejectedHTPPHeader(string $headers, string $httpVersion = '1.0', string $serverScheme = 'http'): void
    {
        $response = $this->customHeadersRequest($headers, 'GET', $httpVersion, $serverScheme);
        $this->assertMatchesRegularExpression('#^HTTP/1.(0|1) 400 #', $response);
    }

    public static function rejectedHTPPHeaderDataProvider(): array
    {
        $cases = [
            ['Custom : hey'],
/// @todo... test: all unsupported chars in header name, header value
        ];

        $out = [];
        foreach ($cases as $line) {
            foreach (self::getSupportedServerSchemes() as $serverScheme) {
                foreach (['1.0', '1.1'] as $protocolversion) {
                    $out[] = $line + [$protocolversion, $serverScheme];
                }
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
}
