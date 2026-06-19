<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/// @todo... bring back support for collecting code coverage of code executed via http calls
abstract class ProxyTestCase extends TestCase
{
    protected string|null $testId;
    /** @var boolean */
    //protected $collectCodeCoverageInformation;
    /** @var string */
    //protected $coverageScriptUrl;
    protected static string|null $randId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set up a database connection or other fixture which needs to be available...

        self::$randId = uniqid();
        file_put_contents(sys_get_temp_dir() . '/phpunit_rand_id.txt', self::$randId);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_file(sys_get_temp_dir() . '/phpunit_rand_id.txt')) {
            unlink(sys_get_temp_dir() . '/phpunit_rand_id.txt');
        }
        self::$randId =null;

        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();

        // make the test name a nice filename
        $this->testId = str_replace([' ', '#'], '_', $this->nameWithDataSet());

        /// @todo...
        // assumes HTTPURI to be in the form /server.php?etc...
        //$this->coverageScriptUrl = $this->getServerBaseUri() . preg_replace('|/server\.php(\?.*)?|', '/phpunit_coverage.php', $this->getServerPath());
    }

    public function tearDown(): void
    {
        $this->testId = null;

        parent::tearDown();
    }

    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     */
    protected function request(array $options, string $method = 'GET', string $path = '', string $clientPreferredType='any'): ResponseInterface
    {
        $client = $this->getClient($clientPreferredType);
        return $client->request($method, trim($path) === '' ? $this->getProxyPath() : $path, $options);
    }

    /**
     * @throws \Exception
     * @todo allow DataProvider functions that iterate the tests over http features, such as req/resp compression, charsets, etc...
     */
    protected function getClient(string $preferredType='any'): HttpClientInterface
    {
/// @todo... allow the user to force usage of proxy in either "forward" or "reverse" mode - check the format supported for `proxy`
        $options = [
            'base_uri' => $this->getServerBaseUri(),
            //'proxy' => $this->getProxyBaseUri() . $this->getProxyPath(),
            'query' => [
                'YAWAF_LOG_FILE' => $this->testId . '.log',
                'YAWAF_TRACE_FILE' => $this->testId . '.trace',
            ],
            'headers' => [
                'Cookie' => 'PHPUNIT_RANDOM_TEST_ID=' . self::$randId,
            ],
        ];

        switch ($preferredType) {
            case 'any':
                return HttpClient::create($options);
            case 'curl':
                // the constructor already checks for the curl extension - no need to do it here
                return new CurlHttpClient($options);
            case 'native':
                return new NativeHttpClient($options);
            default:
                throw new \Exception("Unsupported preferred client type: '$preferredType'");
        }
    }

    protected function getServerBaseUri(): string
    {
        return $this->buildUrl([
            'scheme' => $_ENV['HTTPSERVER_PROTOCOL'],
            'host' => $_ENV['HTTPSERVER_HOST'],
            'port' => $_ENV['HTTPSERVER_PORT'],
        ]);
    }

    protected function getServerPath(): string
    {
        return $_ENV['HTTPSERVER_PATH'];
    }

    protected function getProxyBaseUri(): string
    {
        return $this->buildUrl([
            'scheme' => $_ENV['PROXY_PROTOCOL'],
            'host' => $_ENV['PROXY_HOST'],
            'port' => $_ENV['PROXY_PORT'],
        ]);
    }

    protected function getProxyPath(): string
    {
        return $_ENV['PROXY_PATH'];
    }

    /**
     * Generate URL from its components (i.e., opposite of built-in php function, parse_url())
     */
    protected function buildUrl(array $components): string
    {
        $url = ! empty($components['scheme']) ? $components['scheme'] . '://' : '';

        if ( ! empty($components['username']) && ! empty($components['password'])) {
            $url .= $components['username'] . ':' . $components['password'] . '@';
        }

        $url .= $components['host'] ??  '';

        if ( ! empty($components['port']) &&
            (($components['scheme'] === 'http' && $components['port'] !== 80) ||
                ($components['scheme'] === 'https' && $components['port'] !== 443))
        ) {
            $url .= ':' . $components['port'];
        }

        if ( ! empty($components['path'])) {
            $url .= $components['path'];
        }

        if ( ! empty($components['query'])) {
            $url .= '?' . http_build_query($components['query']);
        }

        if ( ! empty($components['fragment'])) {
            $url .= '#' . $components['fragment'];
        }

        return $url;
    }

    protected static function getSupportedClientTypes(): array
    {
        return extension_loaded('curl') ? ['native', 'curl'] : ['native'];
    }

    protected function getTestDetails(ResponseInterface $response): string
    {
        $out = $this->getServerSideRequestTrace();
        if ($out != '') {
            $out = "\nRequest received by the server:\n$out";
        } else {
            $out = (string)$out;
        }
        $log = $this->getServerSideTestLog();
        if ($out != '') {
            $out .= "\nServer log:\n$log";
        }
        $out .= "\nResponse received by the test code:\n" . $this->response2Log($response);
        return $out . "\n";
    }

    protected function getServerSideRequestTrace(): string|null|false
    {
        $serverSideTraceFile = sys_get_temp_dir() . '/' . $this->testId . '.trace';
        if (is_file($serverSideTraceFile)) {
            return file_get_contents($serverSideTraceFile);
        }
        return null;
    }

    protected function getServerSideTestLog(): string|null|false
    {
        $serverSideLogFile = sys_get_temp_dir() . '/' . $this->testId . '.log';
        if (is_file($serverSideLogFile)) {
            return file_get_contents($serverSideLogFile);
        }
        return null;
    }

    protected function response2Log(ResponseInterface $response): string
    {
        /// @todo can we improve the fidelity of the response dump?
        $out = 'HTTP/x.y ' . $response->getStatusCode() . " ...\n";
        foreach ($response->getHeaders(false) as $name => $values) {
            $out .= ucwords($name, " \t\r\n\f\v-") . ': ' . implode(',', $values) . "\n";
        }
        $out .= "\n" . $response->getContent(false);
        return $out;
    }
}
