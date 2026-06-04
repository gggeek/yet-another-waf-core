<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/// @todo declare dependency on SmokeTest
class AB_MatchingTest extends ProxyTestCase
{
    #[DataProvider('invalidRulesDataProvider')]
    public function testInvalidRules($configAsString)
    {
        $client = $this->getClient();
        $response = $client->request('GET', $this->getProxyPath(), ['query' => ['YAWAF_CONFIG' => $configAsString]]);
        $this->assertEquals(TestProxy::ERROR_STATUS_CODE, $response->getStatusCode());
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message']);
    }

    public static function invalidRulesDataProvider(): array
    {
        return[
            ['null'],
            ['true'],
            ['false'],
            [0],
            [1],
            [1.5],
            ['not a json array string'],
        ];
    }
}
