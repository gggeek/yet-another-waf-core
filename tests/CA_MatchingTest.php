<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/// @todo declare dependency on SmokeTest
class CA_MatchingTest extends ProxyTestCase
{
    #[DataProvider('invalidRulesDataProvider')]
    public function testInvalidRules(string $configAsString, string|null $clientType = null, string $proxyScheme = 'http',
       string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => $configAsString]],
            'GET',
            '',
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        // force the response to be fully retrieved, without throwing in case of errors
        $response->getContent(false);

        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(TestProxy::ERROR_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        $this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message', 'file', 'line'], $failureMessage);
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
/// @todo... add tests for empty "req_match", "resp_match", non-array req_filters and resp_filters and all other illegal combos
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
    public function testPassingGetRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
       string|null $upstreamClientType = null, string $serverScheme = 'http')
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
            ['headers' => ['X-YAWAF-Config-File' => $configFileName] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        // force the response to be fully retrieved, without throwing in case of errors
        $response->getContent(false);

        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
        $this->assertEquals($response->toArray(false)['result'], TestServer::DEFAULT_RESPONSE['result'], $failureMessage);
    }

    public static function passingGetRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('get', 'passing');
    }

    #[DataProvider('failingGetRulesDataProvider')]
    public function testFailingGetRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => $configFileName] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );
        // force the response to be fully retrieved, without throwing in case of errors
        $response->getContent(false);

        /// @todo... given the async nature of Sf http client, pass a stringable ob
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
        foreach (scandir($rootDir) as $fileName) {
            if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                foreach (self::getCommonDataProviderOptions() as $opts) {
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
            foreach (self::getSupportedProxyClientTypes() as $upstreamClientType) {
                if ($serverScheme === 'unix' && ($upstreamClientType === 'guzzle' || $upstreamClientType === 'sfhc_native')) {
                    continue;
                }
                foreach (self::getSupportedProxySchemes() as $proxyScheme) {
                    foreach (self::getSupportedClientTypes() as $clientType) {
                        if ($proxyScheme === 'unix' && $clientType === 'native') {
                            continue;
                        }
                        $out[] = [$clientType, $proxyScheme, $upstreamClientType, $serverScheme];
                    }
                }
            }
        }
        return $out;
    }

    protected function getCommonRequestHeaders(): array
    {
        return [
            'X-Test-1' => 'Hello',
            'X-Test-2' => 1,
            'X-Test-3' => 0,
            'X-Test-4' => 0.5,
            'X-Test-5' => true,
            'X-Test-6' => false, // serialized as empty string
            'X-Test-7' => null,  // serialized as empty string
            'X-Test-8' => ['hi', 'there'],
            'X-Test-9' => '_ :;.,\/"\'?!(){}[]@<>=-+*#$&`|~^%',
        ];
    }

    protected function getCommonQueryString(): string
    {
        return '=yes&n=no&true=true&true=false=1=1&0=0&0.1=0.1&array[]=one&array[]=two';
    }
}
