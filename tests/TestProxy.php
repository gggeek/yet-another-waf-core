<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Proxy\Server;

/// @to rename to testFirewall?
class TestProxy extends Server
{
    const DEFAULT_UPSTREAMS = [
        'http' => 'http://127.0.0.1/server.php',
    ];
    const ACCESS_DENIED_STATUS_CODE = 403;
    const ACCESS_DENIED_RESPONSE = ['result' => 'Access denied'];
    const ERROR_STATUS_CODE = 500;
    const ERROR_RESPONSE = ['result' => 'Error'];

    protected function deniedResponse(ServerRequestInterface $request, \Throwable|null $e = null): ResponseInterface
    {
        return new Response(
            self::ACCESS_DENIED_STATUS_CODE,
            ['content-type' => 'application/json'],
            json_encode(self::ACCESS_DENIED_RESPONSE));
    }

    protected function errorResponse(ServerRequestInterface|null $request = null, \Throwable|null $e = null): ResponseInterface
    {
        return self::getErrorResponse($e);
    }

    public static function getErrorResponse(\Throwable|null $e = null): ResponseInterface
    {
        return new Response(
            self::ERROR_STATUS_CODE,
            ['content-type' => 'application/json'],
            json_encode(self::ERROR_RESPONSE + ($e !== null ? ['message' => $e->getMessage()] : []))
        );
    }
}
