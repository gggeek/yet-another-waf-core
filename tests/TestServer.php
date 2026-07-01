<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use GuzzleHttp\Psr7\ServerRequest as GuzzleServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use YAWAF\Core\ServerRequest\Psr7\Creator as ServerRequestCreator;
use YAWAF\Core\Stdlib;

class TestServer
{
    const DEFAULT_RESPONSE = ['result' => 'OK', '_GET' => [], '_POST' => [], '_COOKIE' => [], 'getallheaders' => null,
        'getHeadersFromServer' => [], 'serverRequest' => null];

    /**
     * Echoes a json payload with as much info as possible about the request received, to help testing
     */
    public function respond(int|string $statusCode = 200, string $serverRequestLibrary = 'yawaf'): void
    {
        switch ((int)$statusCode) {
            case 301:
            case 302:
            case 303:
            case 307:
            case 308:
                $this->displayRedirectResponse((int)$statusCode);
                break;
            case 200:
            default:
                $this->displayInfoResponse($serverRequestLibrary);
        }
    }

    protected function displayRedirectResponse(int $statusCode, string $location = '/server.php'): void
    {
        http_response_code($statusCode);
        header("Location: $location");
    }

    protected function displayInfoResponse(string $serverRequestLibrary = 'yawaf'): void
    {
        $serverRequest = $this->buildServerRequest($serverRequestLibrary);

        $response = array_merge(
            self::DEFAULT_RESPONSE,
            [
                '_GET' => $_GET,
                '_POST' => $_POST,
                '_COOKIE' => $_COOKIE,
                /// @todo add php://input if $_POST is empty and/or the request is not GET / based on content-type req. header
                /// @todo add other bits of $_SERVER and $_ENV that we know are used by ServerRequestCreator::fromGlobals
                /// @todo what about $_FILES?
                'getHeadersFromServer' => Stdlib::getHeadersFromServer($_SERVER),
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
            header("Content-Length: " . strlen($response));
        } else {
            echo $response;
        }
    }

    /**
     * @todo any other well known libraries we could use?
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
                throw new Exception("Unsupported library for creating a ServerRequestInterface instance: '$library'");
        }
    }
}
