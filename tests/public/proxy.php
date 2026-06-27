<?php
declare(strict_types=1);

// *** A waf proxy to be used by unit tests. Forwards all requests to a fixed upstream.
// ***
// *** _Do not use for anything else!_ ***
// ***

require __DIR__ . '/../../vendor/autoload.php';

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Dotenv\Dotenv;
use YAWAF\Core\Firewall\FirewallFactory;
use YAWAF\Core\Logger\FileLogger;
use YAWAF\Core\Middleware\Dispatcher;
use YAWAF\Core\Middleware\Tracer;
use YAWAF\Core\Proxy\FixedUpstreamProxy;
use YAWAF\Core\Psr7\ServerRequest\Creator as ServerRequestCreator;
use YAWAF\Core\Tests\TestProxy;

$proxy = new ProxyPage();
$logger = $proxy->preflight();
$proxy->proxyRequest($logger);
$proxy->postflight();

class ProxyPage
{
    protected string|null $phpunitSeleniumTestId;

    public function preflight(): LoggerInterface|null
    {
        // In case this file is made available on an open-access server, avoid it being useable by anyone who can not
        // also write a specific file to disk.
        // NB: keep filename, cookie name in sync with the code within the TestCase classes sending http requests to this file
        $idFile = sys_get_temp_dir() . '/phpunit_rand_id.txt';
        $randId = $_COOKIE['PHPUNIT_RANDOM_TEST_ID'] ?? '';
        $fileId = file_exists($idFile) ? file_get_contents($idFile) : '';
        if ($randId == '' || $fileId == '' || $fileId !== $randId) {
            header('HTTP/1.1 400 Bad Request');
            die('This url can only be accessed by the test suite');
        }

        // Make errors always visible
        ini_set('display_errors', true);
        error_reporting(E_ALL);

        // Out-of-band information: let the client manipulate the page operations
        if (isset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']) && extension_loaded('xdebug')) {
            // NB: this has to be kept in sync with phpunit_coverage.php
            $GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] = sys_get_temp_dir() . '/yawaf_coverage';
            if (!is_dir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'])) {
                mkdir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY']);
                chmod($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'], 0777);
            }

            include_once __DIR__ . '/../PhpunitSelenium/prepend.php';

            $this->phpunitSeleniumTestId = $_COOKIE['PHPUNIT_SELENIUM_TEST_ID'];
            $this->removeCookieFromEnv('PHPUNIT_SELENIUM_TEST_ID');
        } else {
            $this->phpunitSeleniumTestId = null;
        }

        // Allow the caller to pick a set of configs which differ based on the upstream webserver in use
        // NB: make sure to allow usage of a proxy running on webserver X and upstream running on webserver Y
        $dotenv = new Dotenv();
        $_ENV['SERVER_TYPE'] = 'nginx';
        if (isset($_SERVER['HTTP_X_YAWAF_SERVER_TYPE']) && in_array($_SERVER['HTTP_X_YAWAF_SERVER_TYPE'], ['apache', 'frankenphp'])) {
            $_ENV['SERVER_TYPE'] = $_SERVER['HTTP_X_YAWAF_SERVER_TYPE'];
        }
        $dotenv->loadEnv(__DIR__.'/../.env', 'SERVER_TYPE');

        // set up a logger whose output can be inspected by the caller
        $logger = null;
        if (array_key_exists('HTTP_X_YAWAF_LOG_FILE', $_SERVER) && trim($_SERVER['HTTP_X_YAWAF_LOG_FILE']) !== '') {
            $logFileName = sys_get_temp_dir() . '/' . basename($_SERVER['HTTP_X_YAWAF_LOG_FILE']);
            /// @todo should we allow the logs + traces to be stored in a custom dir, making it easy to map it to the host filesystem?
            if (file_exists($logFileName)) {
                file_put_contents($logFileName, '');
            }
            $logger = new FileLogger($logFileName, LogLevel::DEBUG);
            $logger->debug("Loaded .env config for SERVER_TYPE: {$_ENV['SERVER_TYPE']}");
        }

        return $logger;
    }

    public function postflight(): void
    {
        if ($this->phpunitSeleniumTestId !== null) {
            $_COOKIE['PHPUNIT_SELENIUM_TEST_ID'] = $this->phpunitSeleniumTestId;
            include_once __DIR__ . '/../PhpunitSelenium/append.php';
        }
    }

    public function proxyRequest($logger): void
    {
        $emitter = new SapiEmitter();

        try {
            $firewallFactory = new FirewallFactory($logger);
            $config = array_key_exists('HTTP_X_YAWAF_CONFIG', $_SERVER) ? trim($_SERVER['HTTP_X_YAWAF_CONFIG']) : '';
            $configFile = array_key_exists('HTTP_X_YAWAF_CONFIG_FILE', $_SERVER) ? trim($_SERVER['HTTP_X_YAWAF_CONFIG_FILE']) : '';
            if ($configFile !== '') {
                if ($config !== '') {
                    throw new \Exception("Can not use at the same time headers X-YAWAF-CONFIG and X-YAWAF-CONFIG-FILE");
                }
                if (!$this->fileIsInTestsDir('configs/' . $configFile)) {
                    throw new \Exception("Can not use config file defined in GET var YAWAF_CONFIG_FILE: outside tests root");
                }
                $firewall = $firewallFactory->fromConfigFile(__DIR__ . '/../configs/' . $configFile);
            } else {
                if ($config !== '') {
                    $logger->info('Loading firewall configuration from string received as query string arg YAWAF_CONFIG');
                }
                $firewall = $firewallFactory->fromConfigString($config);
            }

            if (array_key_exists('HTTP_X_YAWAF_TRACE_FILE', $_SERVER) && trim($_SERVER['HTTP_X_YAWAF_TRACE_FILE']) !== '') {
                $traceFileName = sys_get_temp_dir() . '/' . basename($_SERVER['HTTP_X_YAWAF_TRACE_FILE']);
                if (file_exists($traceFileName)) {
                    file_put_contents($traceFileName, '');
                }
                $firewall = new Dispatcher([new Tracer($traceFileName), $firewall]);
            }

            // allow this to be set via a custom http header, to test http:// vs https:// vs tcp:// vs unix:/
            if (array_key_exists('HTTP_X_YAWAF_UPSTREAM_SCHEME', $_SERVER) && trim($_SERVER['HTTP_X_YAWAF_UPSTREAM_SCHEME']) !== '') {
                $upstream = TestProxy::getUpstream($_SERVER['HTTP_X_YAWAF_UPSTREAM_SCHEME']);
            } else {
                $upstream = TestProxy::getUpstream();
            }

            // in case these are set, they might interfere with the configuration of the Client that gets built
            // NB: HTTP_PROXY uppercase should not be used by any clients, as it can be spoofed by an http header from clients...
            unset($_SERVER['http_proxy'], $_SERVER['HTTP_PROXY'], $_SERVER['https_proxy'], $_SERVER['HTTPS_PROXY'], $_SERVER['no_proxy'], $_SERVER['NO_PROXY']);

            if (array_key_exists('HTTP_X_YAWAF_UPSTREAM_CLIENT_TYPE', $_SERVER) && trim($_SERVER['HTTP_X_YAWAF_UPSTREAM_CLIENT_TYPE']) !== '') {
                $httpClient = TestProxy::createUpstreamClient($_SERVER['HTTP_X_YAWAF_UPSTREAM_CLIENT_TYPE']);
            } else {
                $httpClient = null;
            }

            $upstreamConnector = new FixedUpstreamProxy($upstream, $httpClient, $logger);
            $proxy = new TestProxy($firewall, $upstreamConnector, $logger);

            $serverRequest = $this->fromGlobals();
            $response = $proxy->handle($serverRequest);
            $emitter->emit($response);

        } catch (\Throwable $e) {
            $logger?->critical($e->getMessage());
            $emitter->emit(TestProxy::getErrorResponse($e));
            exit();
        }
    }

    /**
     * Clean up ("patch") the data we allow the Proxy to handle - remove test-managing headers and cookies.
     * NB: calling this results in manipulation of $_SERVER and co.
     */
    protected function fromGlobals()
    {
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_X_YAWAF_')) {
                unset($_SERVER[$name]);
            }
        }

        foreach ($_COOKIE as $name => $value) {
            if (str_starts_with($name, 'PHPUNIT_')) {
                $this->removeCookieFromEnv($name);
            }
        }

        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            //$psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );
        $serverRequest = $creator->fromGlobals();

        return $serverRequest;
    }

    protected function fileIsInTestsDir($fileName): bool
    {
        return str_starts_with(realpath(__DIR__ . '/../' . $fileName), realpath(__DIR__ . '/..'));
    }

    protected function removeCookieFromEnv($cookieName)
    {
        unset($_COOKIE[$cookieName]);
/// @todo... patch as well $_SERVER['HTTP_COOKIE'] for consistency
    }
}
