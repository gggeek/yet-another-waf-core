<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use YAWAF\Core\ServerRequestCreator;
use YAWAF\Core\Stdlib;

class TestServer
{
    const DEFAULT_RESPONSE = ['result' => 'OK', '_GET' => [], '_POST' => [], '_COOKIE' => [], 'getallheaders' => null,
        'getHeadersFromServer' => [], 'serverRequest' => null];

    /**
     * Echoes a json payload with as much info as possible about the request received, to help testing
     */
    public function respond(): void
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory, // ServerRequestFactory
            $psr17Factory, // UriFactory
            $psr17Factory, // UploadedFileFactory
            $psr17Factory  // StreamFactory
        );
        $serverRequest = $creator->fromGlobals();


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
                    'requestTarget' => $serverRequest->getrequestTarget(),
                    'URI' => $serverRequest->getURI(),
                    'attributes' => $serverRequest->getAttributes(),
                    'cookieParams' => $serverRequest->getCookieParams(),
                    'queryParams' => $serverRequest->getQueryParams(),
                    'uploadedFiles' => $serverRequest->getUploadedFiles(),
                    'parsedBody' => $serverRequest->getParsedBody(),
                ]
            ]
        );
        if (function_exists('getallheaders')) {
            $response['getallheaders'] = getallheaders();
        }

        header('Content-type: application/json');
        echo json_encode($response);
    }
}
