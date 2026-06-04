<?php

namespace YAWAF\Core\Tests;

use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/// @todo... bring back support for collecting code coverage of code executed via http calls
abstract class ProxyTestCase extends TestCase
{
    /** @var string */
    protected $testId;
    /** @var boolean */
    //protected $collectCodeCoverageInformation;
    /** @var string */
    //protected $coverageScriptUrl;
    /** @var string */
    protected static $randId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set up a database connection or other fixture which needs to be available.
        self::$randId = uniqid();
        file_put_contents(sys_get_temp_dir() . '/phpunit_rand_id.txt', self::$randId);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_file(sys_get_temp_dir() . '/phpunit_rand_id.txt')) {
            unlink(sys_get_temp_dir() . '/phpunit_rand_id.txt');
        }

        parent::tearDownAfterClass();
        self::$randId =null;
    }

    public function setUp(): void
    {
        parent::setUp();

        /// @todo...
        // assumes HTTPURI to be in the form /server.php?etc...
        //$this->coverageScriptUrl = $this->getServerBaseUri() . preg_replace('|/server\.php(\?.*)?|', '/phpunit_coverage.php', $this->getServerPath());
    }

    protected function request(array $options, string $method = 'GET', string $path = ''): ResponseInterface
    {
        $client = $this->getClient();
        return $client->request($method, trim($path) === '' ? $this->getProxyPath() : $path, $options);
    }

    protected function getClient()
    {
/// @todo... allow the user to force usage of proxy in "transparent" mode - ie. as a reverse proxy, as well
///       as asking for a per-test log and trace file, see YAWAF_LOG_FILE, YAWAF_TRACE_FILE
        $options = [
            'base_uri' => $this->getServerBaseUri(),
            'query' => [],
/// @todo... check the format supported for this
            //'proxy' => $this->getProxyBaseUri() . $this->getProxyPath(),
        ];

        /// @todo allow the user to force usage of native vs curl client
        $client = HttpClient::create($options);

        return $client;
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
}
