<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use GuzzleHttp\Psr7\ServerRequest as GuzzleServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use YAWAF\Core\ServerRequest\Psr7\Creator as ServerRequestCreator;
use YAWAF\Core\Stdlib;
use YAWAF\Core\Tracer\RequestTracerTrait;

class TestServer
{
    const DEFAULT_RESPONSE = ['result' => 'OK', '_GET' => [], '_POST' => [], '_COOKIE' => [], 'getallheaders' => null,
        'getHeadersFromServer' => [], 'requestBody' => null, 'serverRequest' => null];

    use RequestTracerTrait;

    protected string $requestPrefix = '';

    public function preflight(): void
    {
/// @todo... enable this after we add support for the PHPUNIT_RANDOM_TEST_ID cookie in the direct-access tests
/*
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
*/
    }

    public function respond(string $requestMethod = 'GET', string $action = 'info', array $actionArgs = []): void
    {
        // avoid php interfering with the server sending out compressed responses, just in case (but we allow the
        // webserver to do the same...)
        ini_set('zlib.output_compression', 0);

        switch ($requestMethod) {
            case 'OPTIONS':
                $this->displayOptionsResponse();
                return;
            case 'TRACE':
                $this->displayTraceResponse();
                return;
        }

        switch ($action) {
            case 'error':
                $this->displayErrorResponse($actionArgs[0] ?? 500);
                break;
            case 'redirect':
                $this->displayRedirectResponse($actionArgs[0] ?? 301);
                break;
            case 'slowloris':
                $this->displaySlowResponse($actionArgs[0] ?? 30);
                break;
            case 'info':
            default:
                $this->displayInfoResponse($actionArgs[0] ?? 'yawaf');
        }
    }

    /**
     * Displays an error response
     */
    protected function displayErrorResponse($statusCode = 500, string $message = ''): void
    {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new \InvalidArgumentException("Unsupported status code for returning an error response");
        }
        http_response_code($statusCode);
        echo $message;
    }

    /**
     * Displays the response to an Options method
     */
    protected function displayOptionsResponse(): void
    {
        http_response_code(204);
        header('Allow: GET, HEAD, OPTIONS, POST, PUT, TRACE');
    }

    /**
     * Displays a redirection response
     */
    protected function displayRedirectResponse($statusCode = 301, string $location = '/server.php'): void
    {
        switch ((int)$statusCode) {
            case 301:
            case 302:
            case 303:
            case 307:
            case 308:
                http_response_code($statusCode);
                header("Location: $location");
                break;
            default:
                throw new \InvalidArgumentException("Unsupported status code for returning a redirection response");
        }
    }

    protected function displaySlowResponse($duration = 30): void
    {
        if ($duration < 0 || $duration > ini_get('max_execution_time')) {
            throw new \InvalidArgumentException("Unsupported duration for returning a slow response");
        }
        $end = microtime(true) + $duration;
        while (microtime(true) < $end) {
            echo '.';
            flush();
            usleep(500000);
        }
        echo ":-)";
    }

    /**
     * Displays the response to a Trace method
     */
    protected function displayTraceResponse(string $serverRequestLibrary = 'yawaf')
    {
        header('Content-Type: message/http');
        echo $this->serializeRequest($this->buildServerRequest($serverRequestLibrary));
    }

    /**
     * Echoes a json payload with as much info as possible about the request received, to help testing
     */
    protected function displayInfoResponse(string $serverRequestLibrary = 'yawaf'): void
    {
        $serverRequest = $this->buildServerRequest($serverRequestLibrary);
        $requestHeaders = Stdlib::getHeadersFromServer($_SERVER);

        $response = array_merge(
            self::DEFAULT_RESPONSE,
            [
                '_GET' => $_GET,
                '_POST' => $_POST,
                '_COOKIE' => $_COOKIE,
                /// @todo add other bits of $_SERVER and $_ENV that we know are used by ServerRequestCreator::fromGlobals
                /// @todo what about $_FILES?
                'getHeadersFromServer' => $requestHeaders,
                'requestBody' => $this->decodeRequestBody(file_get_contents('php://input'), $requestHeaders),
                'serverRequest' => [
                    'method' => $serverRequest->getMethod(),
                    'protocolversion' => $serverRequest->getProtocolVersion(),
                    'requestTarget' => $serverRequest->getRequestTarget(),
                    'URI' => (string)$serverRequest->getURI(),
                    'headers' => $serverRequest->getHeaders(),
                    'attributes' => $serverRequest->getAttributes(),
                    'cookieParams' => $serverRequest->getCookieParams(),
                    'queryParams' => $serverRequest->getQueryParams(),
                    'uploadedFiles' => $serverRequest->getUploadedFiles(),
                    'parsedBody' => $serverRequest->getParsedBody(),
                ]
            ]
        );

        // `getallheaders` is often stubbed, so we check for it with its apache-related name
        if (function_exists('apache_response_headers')) {
            $response['getallheaders'] = apache_response_headers();
        }

        $response = json_encode($response);

        header('Content-type: application/json');
        if (@$_SERVER['REQUEST_METHOD'] === 'HEAD') {
            // (note that this is allowed as per RFC 9110)
            header("Content-Length: " . strlen($response));
        } else {
            echo $response;
        }
    }

    /**
     * @todo any other well known libraries we could use to build the ServerRequestInterface?
     */
    protected function buildServerRequest(string $library = 'yawaf'): ServerRequestInterface
    {
        switch ($library) {
            case 'yawaf':
                $psr17Factory = new Psr17Factory();
                $creator = new ServerRequestCreator(
                    $psr17Factory, // UriFactory
                    $psr17Factory, // UploadedFileFactory
                    $psr17Factory  // StreamFactory
                );
                return $creator->fromGlobals();
            case 'guzzle':
                return GuzzleServerRequest::fromGlobals();
            case 'symfony':
                $factory = new PsrHttpFactory();
                $symfonyRequest = SymfonyRequest::createFromGlobals();
                return $factory->createRequest($symfonyRequest);
            default:
                throw new \InvalidArgumentException("Unsupported library for creating a ServerRequestInterface instance: '$library'");
        }
    }

    protected function decodeRequestBody(string $body, array $requestHeaders): mixed
    {
        if ($body !== '') {
            if (isset($requestHeaders['content-encoding'])) {
                switch ($requestHeaders['content-encoding']) {
                    case 'deflate':
                        $body = @gzuncompress($body);
                        break;
                    case 'gzip':
                        $body = @gzinflate(substr($body, 10, -8));
                        break;
                }
            }

            if (isset($requestHeaders['content-type'])) {
                switch ($requestHeaders['content-type']) {
                    case 'application/json':
                        return @json_decode($body);
                }
            }
        }

        return $body;
    }
}
