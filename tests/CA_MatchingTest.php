<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/// @todo declare dependency on SmokeTest
class CA_MatchingTest extends ProxyTestCase
{
    #[DataProvider('invalidRulesDataProvider')]
    public function testInvalidRules(string $configAsString, string|null $clientType = null, string|null $upstreamClientType = null,
         string $proxyScheme = 'http', string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => $configAsString]],
            'GET',
            '',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(TestProxy::ERROR_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message', 'file', 'line']);
    }

    public static function invalidRulesDataProvider(): array
    {
        $strings = [
            // not an array of rules
            'null',
            'true',
            'false',
            '0',
            '1',
            '1.5',
            'not a json array string',
            // rule 1 is not an array
            '{"rule 1" => true}',
            '{"rule 1" => 0}',
            '{"rule 1" => "a string"}',
            // rule 1 is an array with an invalid body
            '{"rule 1" => ["whatever"]}',
            '{"rule 1" => {"whatever": true}}',
            '{"rule 1" => {"req_match": true}}',
            '{"rule 1" => {"req_match": 0}}',
            '{"rule 1" => {"req_match": {"zzz": true}}}}',
        ];
        $out = [];
        foreach (self::getCommonDataProviderOptions() as $opts) {
            foreach ($strings as $string) {
                $out[] = array_merge([$string], $opts);
            }
        }
        return $out;
    }

    #[DataProvider('passingGetRulesDataProvider')]
    public function testPassingGetRules(string $configFileName, string|null $clientType = null, string|null $upstreamClientType = null,
        string $proxyScheme = 'http', string $serverScheme = 'http')
    {
        // skip test cases which are bound to fail with given configs
        /// @todo this should be more robust/flexible... We should allow the json configs to specify excluded test configs...
        if ($proxyScheme === 'unix' && in_array(basename($configFileName), ['001_client_address_fixed.json', '003_client_address_many.json'])) {
            // avoid the line noise from
            //$this->markTestSkipped('Can not test a client_address match when running the proxy on a unix socket');
            $this->assertEquals(0, 0);
            return;
        }

        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => $configFileName]],
            'GET',
            static::getServerPath() . '?y=yes&n=no&true=true&true=false=1=1&0=0&0.1=0.1&array[]=one&array[]=two',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
        //$this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message']);
    }

    public static function passingGetRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('get', 'passing');
    }

    #[DataProvider('failingGetRulesDataProvider')]
    public function testFailingGetRules(string $configFileName, string|null $clientType = null, string|null $upstreamClientType = null,
         string $proxyScheme = 'http', string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => $configFileName]],
            'GET',
            static::getServerPath() . '?y=yes&n=no&true=true&true=false=1=1&0=0&0.1=0.1&array[]=one&array[]=two',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        $this->assertSame($response->toArray(false), TestProxy::ACCESS_DENIED_RESPONSE, $failureMessage);
    }

    public static function failingGetRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('get', 'failing');
    }

    protected static function getRuleBasedTestDataProviderOptions(string $method, string $status): array
    {
        $rootDir = __DIR__ . "/configs/matchers/$method/$status/";
        $out = [];
        foreach (self::getCommonDataProviderOptions() as $opts) {
            foreach (scandir($rootDir) as $fileName) {
                if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                    $out[] = array_merge(["matchers/$method/$status/$fileName"], $opts);
                }
            }
        }
        return $out;
    }

    /// @todo can we find a better name?
    protected static function getCommonDataProviderOptions(): array
    {
        $out = [];
        foreach (self::getSupportedServerSchemes() as $serverScheme) {
            foreach (self::getSupportedProxySchemes() as $proxyScheme) {
                foreach (self::getSupportedProxyClientTypes() as $upstreamClientType) {
                    if ($serverScheme === 'unix' && ($upstreamClientType === 'guzzle' || $upstreamClientType === 'sfhc_native')) {
                        continue;
                    }
                    foreach (self::getSupportedClientTypes() as $clientType) {
                        if ($proxyScheme === 'unix' && $clientType === 'native') {
                            continue;
                        }
                        $out[] = [$clientType, $upstreamClientType, $proxyScheme, $serverScheme];
                    }
                }
            }
        }
        return $out;
    }
}
