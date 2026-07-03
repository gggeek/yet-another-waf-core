<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

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

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ERROR_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertArrayIsEqualToArrayIgnoringListOfKeys(TestProxy::ERROR_RESPONSE, $response->toArray(false), ['message', 'file', 'line'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(TestProxy::ERROR_STATUS_CODE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
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
            '{"rule 1": true}',
            '{"rule 1": 0}',
            '{"rule 1": "a string"}',
            // rule 1 is an array with an invalid body
            '{"rule 1": ["whatever"]}',
            '{"rule 1": {"whatever": true}}',

            // bad req_match
            '{"rule 1": {"req_match": true}}',
            '{"rule 1": {"req_match": 0}}',
            '{"rule 1": {"req_match": {"zzz": true}}}}',
            // bad resp_match
            '{"rule 1": {"req_match": []}}',
            '{"rule 1": {"resp_match": true}}',
            '{"rule 1": {"resp_match": 0}}',
            '{"rule 1": {"resp_match": {"zzz": true}}}}',
            '{"rule 1": {"resp_match": []}}',
            // bad req_filters
            '{"rule 1": {"req_filters": true}}',
            '{"rule 1": {"req_filters": 0}}',
            '{"rule 1": {"req_filters": {"zzz": true}}}}',
            '{"rule 1": {"req_filters": []}}',
            // bad resp_filters
            '{"rule 1": {"resp_filters": true}}',
            '{"rule 1": {"resp_filters": 0}}',
            '{"rule 1": {"resp_filters": {"zzz": true}}}}',
            '{"rule 1": {"resp_filters": []}}',
            // no matchers
            '{"rule 1": {"req_match": [], "req_action": "allow"}}',
            '{"rule 1": {"req_match": [], "req_action": "deny"}}',
            '{"rule 1": {"resp_match": [], "resp_action": "allow"}}',
            '{"rule 1": {"resp_match": [], "resp_action": "deny"}}',
            '{"rule 1": {"req_match": [], "resp_match": []}}',
            // bad combos
            '{"rule 1": {"req_match": {"host": "localhost"}, "req_action": "unknown"}}',
            '{"rule 1": {"req_match": {"host": "localhost"}, "req_action": "deny", "req_filters: ["one"]}}',
            '{"rule 1": {"req_match": {"host": "localhost"}, "req_action": "deny", "resp_match: {"body": "whatever"}}}',
            '{"rule 1": {"req_match": {"host": "localhost"}, "req_action": "deny", "resp_filters: ["one"]}}',
            '{"rule 1": {"req_match": {"host": "localhost"}, "req_action": "deny", "resp_action: "deny"}}',
            '{"rule 1": {"resp_match": {"body": "whatever"}}}',
            '{"rule 1": {"resp_match": {"body": "whatever"}, "resp_action": "deny, "resp_filters: ["one"]"}}',
        ];
        $out = [];
        /// @todo is this really necessary? We could just pick one type of client / proxy / server for these tests...
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
        if ($proxyScheme === 'unix' && in_array(basename($configFileName), [
            '001_client_address_fixed.json', '003_client_address_many.json',
        ])) {
            // avoid the line noise from the skipped test
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
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
            $this->assertEquals(TestServer::DEFAULT_RESPONSE['result'], $response->toArray(false)['result'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
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
        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    public static function failingGetRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('get', 'failing');
    }

    #[DataProvider('getCommonDataProviderOptions')]
    public function testPortMatcher(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        // skip test cases which are bound to fail with given configs
        /// @todo this should be more robust/flexible...
        if ($proxyScheme === 'unix') {
            // avoid the line noise from the skipped test
            //$this->markTestSkipped('Can not test a client_address match when running the proxy on a unix socket');
            $this->assertEquals(0, 0);
            return;
        }

        $rule = [['port' => ($_ENV['HTTPSERVER_PORT'] != '' ? $_ENV['HTTPSERVER_PORT'] : 80)]];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
            $this->assertEquals(TestServer::DEFAULT_RESPONSE['result'], $response->toArray(false)['result'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    #[DataProvider('getCommonDataProviderOptions')]
    public function testPortMatcherFail(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        // skip test cases which are bound to fail with given configs
        /// @todo this should be more robust/flexible...
        if ($proxyScheme === 'unix') {
            // avoid the line noise from the skipped test
            //$this->markTestSkipped('Can not test a client_address match when running the proxy on a unix socket');
            $this->assertEquals(0, 0);
            return;
        }

        $rule = [['port' => ($_ENV['HTTPSERVER_PORT'] != '' ? ($_ENV['HTTPSERVER_PORT'] + 1) : 79)]];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame($response->toArray(false), TestProxy::ACCESS_DENIED_RESPONSE, $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_STATUS_CODE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    #[DataProvider('getCommonDataProviderOptions')]
    public function testPathMatcher(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        $rule = [['url_path/no_wildcards' => $_ENV['HTTPSERVER_PATH']]];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
            $this->assertEquals(TestServer::DEFAULT_RESPONSE['result'], $response->toArray(false)['result'], $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }

        $rule = [['url_path/no_wildcards' => $_ENV['HTTPSERVER_PATH'] . '/yolo']];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame(TestProxy::ACCESS_DENIED_RESPONSE, $response->toArray(false), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_STATUS_CODE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    #[DataProvider('getCommonDataProviderOptions')]
    public function testPathMatcherFail(string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        $rule = [['url_path/no_wildcards' => $_ENV['HTTPSERVER_PATH'] . '/yolo']];
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config' => json_encode($rule)] + $this->getCommonRequestHeaders()],
            'GET',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
            $this->assertSame($response->toArray(false), TestProxy::ACCESS_DENIED_RESPONSE, $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertSame(TestProxy::ACCESS_DENIED_STATUS_CODE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
    }

    #[DataProvider('passingHeadRulesDataProvider')]
    public function NotReadyYetTestPassingHeadRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        // skip test cases which are bound to fail with given configs
        /// @todo this should be more robust/flexible... We should allow the json configs to specify excluded test configs...
        if ($proxyScheme === 'unix' && in_array(basename($configFileName), [
                '001_client_address_fixed.json', '003_client_address_many.json',
            ])) {
            // avoid the line noise from the skipped test
            //$this->markTestSkipped('Can not test a client_address match when running the proxy on a unix socket');
            $this->assertEquals(0, 0);
            return;
        }

        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => $configFileName] + $this->getCommonRequestHeaders()],
            'HEAD',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(200, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }

        /// @todo... check that resp. body is empty
    }

    public static function passingHeadRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('head', 'passing');
    }

    #[DataProvider('failingHeadRulesDataProvider')]
    public function NotReadyYetTestFailingHeadRules(string $configFileName, string|null $clientType = null, string $proxyScheme = 'http',
        string|null $upstreamClientType = null, string $serverScheme = 'http')
    {
        $response = $this->request(
            ['headers' => ['X-YAWAF-Config-File' => $configFileName] + $this->getCommonRequestHeaders()],
            'HEAD',
            static::getServerPath() . '?' . $this->getCommonQueryString(),
            ['client_type' => $clientType, 'upstream_client_type' => $upstreamClientType, 'proxy_scheme' => $proxyScheme, 'server_scheme' => $serverScheme]
        );

        try {
            $failureMessage = $this->getTestDetails($response);
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        } catch (ExceptionInterface $e) {
            $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, null, 'Exception thrown by the test client while communicating to the proxy: ' . $e->getMessage());
        }
        /// @todo... check that resp. body is empty
    }

    public static function failingHeadRulesDataProvider(): array
    {
        return self::getRuleBasedTestDataProviderOptions('head', 'failing');
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
        return 'y=yes&n=no&true=true&false=false&1=1&0=0&0.1=0.1&array[]=one&array[]=two&surprise';
    }
}
