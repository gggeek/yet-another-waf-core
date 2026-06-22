<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use PHPUnit\Runner\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use YAWAF\Core\Tests\PhpunitSelenium\RemoteCoverageCollector;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/// @todo... bring back support for collecting code coverage of code executed via http calls
abstract class ProxyTestCase extends TestCase
{
    protected string|null $testId;
    protected static string|null $randId;
    /** @var string[] */
    protected static array $testIds = [];

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
        self::$randId = null;

        if (self::shouldCollectCodeCoverageInformation()) {
            self::retrieveRemoteCodeCoverage();
        }
        self::$testIds = [];

        parent::tearDownAfterClass();
    }

    public function setUp(): void
    {
        parent::setUp();

        // make the test name a nice filename
        $this->testId = str_replace([' ', '#'], '_', $this->nameWithDataSet());
        self::$testIds[] = $this->testId;
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
    protected function request(array $requestOptions, string $method = 'GET', string $path = '', array $testOptions = []): ResponseInterface
    {
        $client = $this->getProxyClient([], $testOptions);
        return $client->request($method, static::getServerBaseUri() . (trim($path) === '' ? static::getServerPath() : $path), $requestOptions);
    }

    /**
     * Creates an http client with the given options, making its requests go through the proxy
     * @throws \Exception
     * @todo allow DataProvider functions that iterate the tests over http features, such as req/resp compression, charsets,
     *       etc... (here or ?)
     */
    protected function getProxyClient(array $clientOptions = [], array $testOptions = []): HttpClientInterface
    {
        $clientOptions = $clientOptions + [
                'proxy' => static::getProxyBaseUri(),
            ];
        if (@$testOptions['upstream_client_type'] !== null) {
            $clientOptions['headers'] = ['X-YAWAF-Upstream-Client-Type' => $testOptions['upstream_client_type']] + ($clientOptions['headers'] ?? []);
        }
        if (@$testOptions['server_scheme'] !== null) {
            $clientOptions['headers'] = ['X-YAWAF-Upstream-Scheme' => $testOptions['server_scheme']] + ($clientOptions['headers'] ?? []);
        }

        return $this->getTestClient($clientOptions, $testOptions);
    }

    /**
     * Creates an http client with the given options, adding to its requests http headers used by the test proxy page
     * to drive its operations.
     * @throws \Exception
     */
    protected function getTestClient(array $clientOptions = [], array $testOptions = []): HttpClientInterface
    {
        $cookie = '';
        if (isset($clientOptions['headers']['Cookie'])) {
            $cookie = $clientOptions['headers']['Cookie'] . ';';
        }
        $cookie .= 'PHPUNIT_RANDOM_TEST_ID=' . self::$randId;
        if (self::shouldCollectCodeCoverageInformation()) {
            $cookie .= ';  PHPUNIT_SELENIUM_TEST_ID=' . $this->testId;
        }
        $clientOptions['headers'] = [
                'Cookie' => $cookie,
                'X-YAWAF-Log-File' => $this->testId . '.log',
                'X-YAWAF-Trace-File' => $this->testId . '.trace',
            ] + ($clientOptions['headers'] ?? []);

        return $this->getClient($clientOptions, $testOptions);
    }

    /**
     * Creates an http client with the given options.
     * @throws \Exception
     */
    protected function getClient(array $clientOptions = [], array $testOptions = []): HttpClientInterface
    {
        switch (@$testOptions['client_type']) {
            case 'curl':
                // the constructor already checks for the curl extension - no need to do it here
                return new CurlHttpClient($clientOptions);
            case 'native':
                return new NativeHttpClient($clientOptions);
            case null:
            case 'any':
                return HttpClient::create($clientOptions);
            default:
                throw new \Exception("Unsupported preferred client type: '{$testOptions['preferred_client_type']}'");
        }
    }

    protected static function getServerBaseUri($scheme = 'http'): string
    {
        switch ($scheme) {
            case 'http':
            case 'https':
                return static::buildUrl([
                    'scheme' => $scheme,
                    'host' => $_ENV['HTTPSERVER_HOST'],
                    'port' => $_ENV['HTTPSERVER_PORT'],
                ]);
            case 'unix':
                return 'unix:' . $_ENV['HTTPSERVER_SOCKET'];
            default:
                throw new \Exception("Unsupported server scheme: $scheme");
        }
    }

    protected static function getServerPath(): string
    {
        return $_ENV['HTTPSERVER_PATH'];
    }

    protected static function getProxyBaseUri($scheme = 'http'): string
    {
        switch ($scheme) {
            case 'http':
            case 'https':
                return static::buildUrl([
                    'scheme' => $scheme,
                    'host' => $_ENV['PROXY_HOST'],
                    'port' => $_ENV['PROXY_PORT'],
                ]);
            case 'unix':
                return 'unix:' . $_ENV['PROXY_SOCKET'];
            default:
                throw new \Exception("Unsupported proxy scheme: $scheme");
        }
    }

    /**
     * Only to be used for accessing the proxy endpoint directly
     */
    protected static function getProxyPath(): string
    {
        return $_ENV['PROXY_PATH'];
    }

    /**
     * @throws \Exception
     */
    protected static function getRemoteCoverageBaseUri(): string
    {
        /// @todo allow this to be set via an env var, fall back on server if that is not defined
        return static::getServerBaseUri();
    }

    protected static function getRemoteCoveragePath(): string
    {
        // @todo should we allow this to be set via an env var?
        return '/phpunit_coverage.php';
    }

    public static function clientTypesDataProvider(): array
    {
        $out = [];
        foreach (static::getSupportedClientTypes() as $type) {
            $out[] = [$type];
        }
        return $out;
    }

    /**
     * These are the types of symfony http clients used to query the server/proxy
     * @return string[]
     */
    protected static function getSupportedClientTypes(): array
    {
        return extension_loaded('curl') ? ['native', 'curl'] : ['native'];
    }

    protected static function getSupportedServerSchemes(): array
    {
        $schemes = [];
        if (isset($_ENV['HTTPSERVER_HOST']) && trim($_ENV['HTTPSERVER_HOST']) !== '') {
            $schemes[] = 'http';
        }
        if (isset($_ENV['HTTPSERVER_SOCKET']) && trim($_ENV['HTTPSERVER_SOCKET']) !== '') {
            $schemes[] = 'unix';
        }
        return $schemes;
    }

    protected static function getSupportedProxySchemes(): array
    {
        $schemes = [];
        if (isset($_ENV['PROXY_HOST']) && trim($_ENV['PROXY_HOST']) !== '') {
            $schemes[] = 'http';
        }
        if (isset($_ENV['PROXY_SOCKET']) && trim($_ENV['PROXY_SOCKET']) !== '') {
            $schemes[] = 'unix';
        }
        return $schemes;
    }

    /**
     * NB: we _presume_ that the proxy used to run the tests has installed php-curl, sf-http-client and guzzle
     * @return string[]
     */
    protected static function getSupportedProxyClientTypes(): array
    {
        return ['sfhc_native', 'sfhc_curl', 'guzzle'];
    }

    protected function getTestDetails(ResponseInterface $response): string
    {
        $out = $this->getProxyRequestTrace();
        if ($out != '') {
            $out = "\nRequest received by the proxy (and possibly response generated):\n$out";
        } else {
            $out = (string)$out;
        }
        $log = $this->getProxyTestLog();
        if ($log != '') {
            $out .= "\nServer log:\n$log";
        }
        $out .= "\nResponse received by the test code:\n" . $this->response2Log($response);
        return $out . "\n";
    }

    protected function getProxyRequestTrace(): string|null|false
    {
        $serverSideTraceFile = sys_get_temp_dir() . '/' . $this->testId . '.trace';
        if (is_file($serverSideTraceFile)) {
            return file_get_contents($serverSideTraceFile);
        }
        return null;
    }

    protected function getProxyTestLog(): string|null|false
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

    protected static function shouldCollectCodeCoverageInformation(): bool
    {
        return CodeCoverage::instance()->isActive();
    }

    protected static function retrieveRemoteCodeCoverage(): void
    {
        foreach (self::$testIds as $testId) {
            $collector = new RemoteCoverageCollector(
                static::getRemoteCoverageBaseUri() . static::getRemoteCoveragePath(),
                $testId
            );
            $data = $collector->get();
            if ($data) {
                CodeCoverage::instance()->codeCoverage()->append(RawCodeCoverageData::fromXdebugWithoutPathCoverage($data), $testId);
            }
        }
    }

    /**
     * Generate URL from its components (i.e., opposite of built-in php function, parse_url())
     */
    protected static function buildUrl(array $components): string
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
}
