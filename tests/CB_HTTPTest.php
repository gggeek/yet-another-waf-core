<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use YAWAF\Core\Proxy\Proxy;

/// @todo declare dependency on SmokeTest
class CB_HTTPTest extends ProxyTestCase
{
    #[DataProvider('getCommonDataProviderOptions')]
    public function testSlowUpstream(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        if ($upstreamClientType === 'guzzle_stream') {
            $this->markTestIncomplete('Test known to fail atm with Guzzle/Stream client. See issue #3809...');
        }

        $rule = [['always' => true]];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)]],
            'GET',
            static::getServerPath() . '?action=slowloris&action_args[]=5',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(Proxy::UPSTREAM_TIMEOUT_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            //$this->assertEquals($response->toArray(false)['result'], TestServer::DEFAULT_RESPONSE['result'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(Proxy::UPSTREAM_TIMEOUT_STATUS_CODE, null, 'Exception thrown by client while communicating to the proxy: ' . $e->getMessage());
        }
    }
}
