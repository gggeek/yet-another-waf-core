<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Symfony\Component\HttpClient\HttpClient;

class AA_SmokeTest extends ProxyTestCase
{
    #[DataProvider('clientTypesDataProvider')]
    public function testServer(string $clientType = 'any')
    {
        $client = HttpClient::create(['base_uri' => static::getServerBaseUri()]);
        $response = $client->request('GET', static::getServerPath());
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys(TestServer::DEFAULT_RESPONSE, $response->toArray(false), ['headers']);
    }

    #[DataProvider('clientTypesDataProvider')]
    public function testProxyAsUpstreamNoTestCookie(string $clientType = 'any')
    {
        $client = $this->getClient(['base_uri' => static::getProxyBaseUri()], $clientType);
        $response = $client->request('GET', static::getProxyPath());
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(400, $response->getStatusCode(), $response->getContent(false));
        $this->assertEquals('This url can only be accessed by the test suite', $response->getContent(false));
    }

    #[DataProvider('clientTypesDataProvider')]
    public function testProxyAsUpstreamWithTestCookie(string $clientType = 'any')
    {
        $client = $this->getTestClient(['base_uri' => static::getProxyBaseUri()], $clientType);
        $response = $client->request('GET', static::getProxyPath());
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $response->getContent(false));
        $this->assertEquals(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $response->getContent(false));
    }

    #[DataProvider('clientTypesDataProvider')]
    public function testProxyAsProxyWithoutRules(string $clientType = 'any')
    {
        $response = $this->request([], 'GET', '', $clientType);
        // Without any config, the firewall should return a DENY response
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $response->getContent(false));
        $this->assertEquals(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $response->getContent(false));
    }
}
