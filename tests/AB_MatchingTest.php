<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/// @todo declare dependency on SmokeTest
class AB_MatchingTest extends ProxyTestCase
{
    #[DataProvider('invalidRulesDataProvider')]
    public function testInvalidRules($configAsString, string $clientType='any')
    {
        $response = $this->request(['headers' => ['X-YAWAF-Config' => $configAsString]], 'GET', '', $clientType);
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
            0,
            1,
            1.5,
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
        foreach (self::getSupportedClientTypes() as $clientType) {
            foreach ($strings as $string)
            {
                $out[] = [$string, $clientType];
            }
        }
        return $out;
    }

    #[DataProvider('passingRulesDataProvider')]
    public function testPassingRules(string $configFileName, string $clientType='any')
    {
        $response = $this->request(['headers' => ['X-YAWAF-Config-File' => 'matchers/passing/' . $configFileName]], 'GET', '', $clientType);
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(200, $response->getStatusCode(), $failureMessage);
        //$this->assertArrayIsEqualToArrayIgnoringListOfKeys($response->toArray(false), TestProxy::ERROR_RESPONSE, ['message']);
    }

    public static function passingRulesDataProvider(): array
    {
        $rootDir = __DIR__ . '/configs/matchers/passing/';
        $out = [];
        foreach (self::getSupportedClientTypes() as $clientType) {
            foreach (scandir($rootDir) as $fileName) {
                if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                    $out[] = [$fileName, $clientType];
                }
            }
        }
        return $out;
    }

    #[DataProvider('failingRulesDataProvider')]
    public function testFailingRules(string $configFileName, string $clientType='any')
    {
        $response = $this->request(['headers' => ['X-YAWAF-Config-File' => 'matchers/failing/' . $configFileName]], 'GET', '', $clientType);
        $failureMessage = $this->getTestDetails($response);
        $this->assertEquals(TestProxy::ACCESS_DENIED_STATUS_CODE, $response->getStatusCode(), $failureMessage);
        $this->assertSame($response->toArray(false), TestProxy::ACCESS_DENIED_RESPONSE, $failureMessage);
    }

    public static function failingRulesDataProvider(): array
    {
        $rootDir = __DIR__ . '/configs/matchers/failing/';
        $out = [];
        foreach (self::getSupportedClientTypes() as $clientType) {
            foreach (scandir($rootDir) as $fileName) {
                if (is_file($rootDir . $fileName) && str_ends_with($fileName, '.json')) {
                    $out[] = [$fileName, $clientType];
                }
            }
        }
        return $out;
    }
}
