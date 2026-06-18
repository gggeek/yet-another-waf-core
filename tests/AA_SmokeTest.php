<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Symfony\Component\HttpClient\HttpClient;

class AA_SmokeTest extends ProxyTestCase
{
    public function testServer()
    {
        $client = HttpClient::create(['base_uri' => $this->getServerBaseUri()]);
        $response = $client->request('GET', $this->getServerPath());
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys(TestServer::DEFAULT_RESPONSE, $response->toArray(false), ['headers']);
    }

    public function testProxy()
    {
        $response = $this->request([]);
        // Without any config, the firewall should return a DENY response
        // Note that in case of php errors, the status code will be 200 when display_errors in php.ini is on, and 500 when it is off
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode());
        $this->assertEquals(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false));
    }
}
