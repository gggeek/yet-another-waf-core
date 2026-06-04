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

function fileIsInTestsDir($fileName): bool
{
    return str_starts_with(realpath(__DIR__ . '/..'), realpath(__DIR__ . '/../' . $fileName));
}

$emitter = new SapiEmitter();

try {
    $logger = null;
    // set up a logger whose output can be inspected by the caller
    if (array_key_exists('YAWAF_LOG_FILE', $_GET) && trim($_GET['YAWAF_LOG_FILE']) !== '') {
        if (!fileIsInTestsDir('ci/var/' . $_GET['YAWAF_LOG_FILE'])) {
            throw new \Exception("Can not use trace file defined in GET var YAWAF_LOG_FILE: outside tests root");
        }
        $logger = new FileLogger('ci/var/' . $_GET['YAWAF_LOG_FILE'], LogLevel::DEBUG);
    }

/// @todo... allow this to be set via an env var such as HTTPSERVER, so that it can be injected by the test vm via apache config,
///          or even by the caller via a GET parameter
    $upstream = TestProxy::DEFAULT_UPSTREAM;

    $firewallFactory = new FirewallFactory($logger);
    $config = array_key_exists('YAWAF_CONFIG', $_GET) ? trim($_GET['YAWAF_CONFIG']) : '';
    $configFile = array_key_exists('YAWAF_CONFIG_FILE', $_SERVER) ? trim($_SERVER['YAWAF_CONFIG_FILE']) : '';
    if ($configFile !== '') {
        if ($config !== '') {
            throw new \Exception("Can not use at the same time GET vars YAWAF_CONFIG and YAWAF_CONFIG_FILE");
        }
        if (!fileIsInTestsDir('configs/' . $configFile)) {
            throw new \Exception("Can not use config file defined in GET var YAWAF_CONFIG_FILE: outside tests root");
        }
        $firewall = $firewallFactory->fromConfigFile('configs/' . basename($configFile));
    } else {
        $firewall = $firewallFactory->fromConfigString($config);
    }

    if (array_key_exists('YAWAF_TRACE_FILE', $_GET) && trim($_GET['YAWAF_TRACE_FILE']) !== '') {
        /// @todo... should we default to sys_get_temp_dir instead?
        if (!fileIsInTestsDir('ci/var/' . $_GET['YAWAF_TRACE_FILE'])) {
            throw new \Exception("Can not use trace file defined in GET var YAWAF_TRACE_FILE: outside tests root");
        }
        $firewall = new FilterChain([new Tracer('ci/var/' . $_GET['YAWAF_TRACE_FILE']), $firewall]);
    }

    $proxy = new TestProxy($firewall, $upstream, null, $logger);

    $psr17Factory = new Psr17Factory();
    $creator = new ServerRequestCreator(
        $psr17Factory, // ServerRequestFactory
        $psr17Factory, // UriFactory
        $psr17Factory, // UploadedFileFactory
        $psr17Factory  // StreamFactory
    );

} catch (\Throwable $e) {
    $emitter->emit(TestProxy::getErrorResponse($e));
    exit();
}

$handler = function () use ($creator, $proxy, $emitter) {
    $serverRequest = $creator->fromGlobals();
    $response = $proxy->handle($serverRequest);
    $emitter->emit($response);
};

if (!array_key_exists('FRANKENPHP_WORKER', $_SERVER) || (int)$_SERVER['FRANKENPHP_WORKER'] == 0) {

    $handler();

} else {

    $maxRequests = (int)($_SERVER['MAX_REQUESTS_PER_WORKER'] ?? 0);
    for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {

        // NB: `set_exception_handler` is called only when the worker script ends,
        // which may be unexpected, so we could (should?) catch and handle exceptions inside $handler

        /** @noinspection PhpUndefinedFunctionInspection */
        /** @phpstan-ignore function.notFound */
        $keepRunning = \frankenphp_handle_request($handler);

        // Call the garbage collector to reduce the chances of it being triggered in the middle of a page generation
        /// @todo do this every N requests?
        gc_collect_cycles();

        if (!$keepRunning) break;
    }
}
