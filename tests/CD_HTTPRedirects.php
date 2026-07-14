<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use YAWAF\Core\Proxy\Proxy;

class CD_HTTPRedirects extends ProxyTestCase
{
    /**
     * Test getting back a 30x - without following it.
     * @todo use a custom DataProvider to test 302, 303, 307, 308 responses
     */
    #[DataProvider('getCommonDataProviderOptions')]
    public function testRedirectingUpstream(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http', int $redirectCode = 301)
    {
        $rule = [['always' => true]];
        $response = $this->request(
            [
                'headers' => ['X-YAWAF-Config' => json_encode($rule), 'X-YAWAF-Force-Accept-Encoding' => 'identity'],
                'max_redirects' => 0
            ],
            'GET',
            static::getServerPath() . '?action=redirect&action_args[]=' . $redirectCode,
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals($redirectCode, $response->getStatusCode(), $failureMessage);
            //$this->assertEquals($response->toArray(false)['result'], TestServer::DEFAULT_RESPONSE['result'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(Proxy::UPSTREAM_TIMEOUT_STATUS_CODE, null, 'Exception thrown by client while communicating to the proxy: ' . $e->getMessage());
        }
    }
}
