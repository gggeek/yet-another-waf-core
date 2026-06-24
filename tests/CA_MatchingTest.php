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
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message']);
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

    #[DataProvider('passingRulesDataProvider')]
    public function testPassingRules(string $configFileName, string|null $clientType = null, string|null $upstreamClientType = null,
        string $proxyScheme = 'http', string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => 'matchers/passing/' . $configFileName]],
            'GET',
            '',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
        //$this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message']);
    }

    public static function passingRulesDataProvider(): array
    {
        $rootDir = __DIR__ . '/configs/matchers/passing/';
        $out = [];
        foreach (self::getCommonDataProviderOptions() as $opts) {
            foreach (scandir($rootDir) as $fileName) {
                if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                    $out[] = array_merge([$fileName], $opts);
                }
            }
        }
        return $out;
    }

    #[DataProvider('failingRulesDataProvider')]
    public function testFailingRules(string $configFileName, string|null $clientType = null, string|null $upstreamClientType = null,
         string $proxyScheme = 'http', string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => 'matchers/failing/' . $configFileName]],
            'GET',
            '',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        $this->assertSame($response->toArray(false), TestProxy::ACCESS_DENIED_RESPONSE, $failureMessage);
    }

    public static function failingRulesDataProvider(): array
    {
        $rootDir = __DIR__ . '/configs/matchers/failing/';
        $out = [];
        foreach (self::getCommonDataProviderOptions() as $opts) {
            foreach (scandir($rootDir) as $fileName) {
                if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                    $out[] = array_merge([$fileName], $opts);
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
