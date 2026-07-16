<?php

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ZZTest extends ProxyTestCase
{
    static protected int $clientPort = 31000;

    #[DataProvider('getCommonDataProviderOptions')]
    public function testClientPortMatcher(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        if (isset($_SERVER['GITHUB_ACTIONS'])) {
            $this->markTestSkipped('Client Port Matching testing is unreliable on GitHub. Skipping it...');
        }

        // skip test cases which are bound to fail with given configs
        /// @todo use a custom DataProvider
        if ($proxyScheme === 'unix' || $clientType === 'native') {
            $this->assertSame(0, 0);
            return;
        }

        // NB: we try to make sure that the port is not use, by increasing it on every pass of the test.
        // Atm this kind "generally" works, helped by the fact that we tell curl to use http 1.0 for this test, which
        // means connections getting closed immediately after use instead of being kept open for reuse.
        // Nonetheless, there is no real guarantee that self::$clientPort + 1 is available at this very moment...
        self::$clientPort += 1;

        if ($_ENV['SERVER_TYPE'] === 'nginx') {
            $httpVersion = '1.1';
        } else {
            $httpVersion = '1.0';
        }

        $rule = [['client_port' => self::$clientPort]];
        $response = $this->request(
            [
                'headers' => ['X-YAWAF-Config' => json_encode($rule), 'Connection' => 'close'], // + $this->getCommonRequestHeaders(),
                'http_version' => $httpVersion,
                'bindto' => '127.0.0.1:' . self::$clientPort
            ],
            'GET',
            static::getServerPath() . '?' . $this->testId, // . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertResponseHasStatusCode(200, $response, $failureMessage);
            $this->assertResponseHasKnownJsonBody($response, $failureMessage);
var_dump($response->toArray(false));
        } catch (ExceptionInterface $e) {
            $this->assertSame(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

}
