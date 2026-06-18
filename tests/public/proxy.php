<?php
declare(strict_types=1);

// *** An http filtering proxy to be used by unit tests.
// ***
// *** _Do not use for anything else!_ ***
// ***

require __DIR__ . '/../../vendor/autoload.php';

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Log\LogLevel;
use YAWAF\Core\Filter\Bidirectional\FilterChain;
use YAWAF\Core\Filter\Bidirectional\Tracer;
use YAWAF\Core\Firewall\FirewallFactory;
use YAWAF\Core\Logger\FileLogger;
use YAWAF\Core\Tests\TestProxy;

ini_set('error_reporting', E_ALL);
ini_set('display_errors', true);

ProxyPage::preflight();
ProxyPage::proxyRequest();
ProxyPage::postflight();

class ProxyPage
{
    protected static string|null $phpunitSeleniumTestId;

    public static function preflight(): void
    {
        // In case this file is made available on an open-access server, avoid it being useable by anyone who can not
        // also write a specific file to disk.
        // NB: keep filename, cookie name in sync with the code within the TestCase classes sending http requests to this file
        $idFile = sys_get_temp_dir() . '/phpunit_rand_id.txt';
        $randId = $_COOKIE['PHPUNIT_RANDOM_TEST_ID'] ?? '';
        $fileId = file_exists($idFile) ? file_get_contents($idFile) : '';
        if ($randId == '' || $fileId == '' || $fileId !== $randId) {
            /// @todo add a 403 access-denied / 400 bad-request header?
            //header('HTTP/1.1 500 Internal Server Error');
            die('This url can only be accessed by the test suite');
        }

        // Make errors always visible
        ini_set('display_errors', true);
        error_reporting(E_ALL);

        // Out-of-band information: let the client manipulate the page operations
        if (isset($_COOKIE['PHPUNIT_SELENIUM_TEST_ID']) && extension_loaded('xdebug')) {
            // NB: this has to be kept in sync with phunit_coverage.php
            $GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'] = sys_get_temp_dir() . '/yawaf_coverage';
            if (!is_dir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'])) {
                mkdir($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY']);
                chmod($GLOBALS['PHPUNIT_COVERAGE_DATA_DIRECTORY'], 0777);
            }

/// @todo vendorize this
            include_once __DIR__ . '/../../vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/prepend.php';

            self::$phpunitSeleniumTestId = $_COOKIE['PHPUNIT_SELENIUM_TEST_ID'];
        } else {
            self::$phpunitSeleniumTestId = null;
        }
    }

    public static function postflight(): void
    {
        if (self::$phpunitSeleniumTestId !== null) {
/// @todo vendorize this
            include_once __DIR__ . '/../../vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/append.php';
        }
    }

    public static function proxyRequest(): void
    {
        $logger = null;
        $emitter = new SapiEmitter();

        try {
            // set up a logger whose output can be inspected by the caller
            if (array_key_exists('YAWAF_LOG_FILE', $_GET) && trim($_GET['YAWAF_LOG_FILE']) !== '') {
                $logFileName = sys_get_temp_dir() . '/' . basename($_GET['YAWAF_LOG_FILE']);
                /// @todo should we allow the logs + traces to be stored in a custom dir, making it easy to map it to the host filesystem?
                //if (!self::fileIsInTestsDir('ci/var/' . $_GET['YAWAF_LOG_FILE'])) {
                //    throw new \Exception("Can not use trace file defined in GET var YAWAF_LOG_FILE: outside tests root");
                //}
                if (file_exists($logFileName)) {
                    file_put_contents($logFileName, '');
                }
                $logger = new FileLogger($logFileName, LogLevel::DEBUG);
            }

/// @todo... allow this to be set via a GET parameter, to test http:// vs tcp:// vs unix://
            $upstream = TestProxy::DEFAULT_UPSTREAM;

            $firewallFactory = new FirewallFactory($logger);
            $config = array_key_exists('YAWAF_CONFIG', $_GET) ? trim($_GET['YAWAF_CONFIG']) : '';
            $configFile = array_key_exists('YAWAF_CONFIG_FILE', $_GET) ? trim($_GET['YAWAF_CONFIG_FILE']) : '';
            if ($configFile !== '') {
                if ($config !== '') {
                    throw new \Exception("Can not use at the same time GET vars YAWAF_CONFIG and YAWAF_CONFIG_FILE");
                }
                if (!self::fileIsInTestsDir('configs/' . $configFile)) {
                    throw new \Exception("Can not use config file defined in GET var YAWAF_CONFIG_FILE: outside tests root");
                }
                $firewall = $firewallFactory->fromConfigFile(__DIR__ . '/../configs/' . $configFile);
            } else {
                if ($config !== '') {
                    $logger->info('Loading firewall configuration from string received as query string arg YAWAF_CONFIG');
                }
                $firewall = $firewallFactory->fromConfigString($config);
            }

            if (array_key_exists('YAWAF_TRACE_FILE', $_GET) && trim($_GET['YAWAF_TRACE_FILE']) !== '') {
                //if (!self::fileIsInTestsDir('ci/var/' . $_GET['YAWAF_TRACE_FILE'])) {
                //    throw new \Exception("Can not use trace file defined in GET var YAWAF_TRACE_FILE: outside tests root");
                //}
                $traceFileName = sys_get_temp_dir() . '/' . basename($_GET['YAWAF_TRACE_FILE']);
                if (file_exists($traceFileName)) {
                    file_put_contents($traceFileName, '');
                }
                $firewall = new FilterChain([new Tracer($traceFileName), $firewall]);
            }

            // clean up the data we forward to the server
            foreach ($_GET as $name => $value) {
                if (str_starts_with($name, 'YAWAF_')) {
                    unset($_GET[$name]);
                }
            }
            foreach ($_COOKIE as $name => $value) {
                if (str_starts_with($name, 'PHPUNIT_')) {
                    unset($_GET[$name]);
                }
            }

            $proxy = new TestProxy($firewall, $upstream, null, $logger);

            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, // ServerRequestFactory
                $psr17Factory, // UriFactory
                $psr17Factory, // UploadedFileFactory
                $psr17Factory  // StreamFactory
            );

            $serverRequest = $creator->fromGlobals();
            $response = $proxy->handle($serverRequest);
            $emitter->emit($response);

        } catch (\Throwable $e) {
            $logger?->critical($e->getMessage());
            $emitter->emit(TestProxy::getErrorResponse($e));
            exit();
        }
    }

    protected static function fileIsInTestsDir($fileName): bool
    {
        return str_starts_with(realpath(__DIR__ . '/../' . $fileName), realpath(__DIR__ . '/..'));
    }
}
