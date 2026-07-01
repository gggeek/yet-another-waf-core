<?php
declare(strict_types=1);

namespace YAWAF\Core\Tests;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use YAWAF\Core\Proxy\FilteringProxy;
use YAWAF\Core\UpstreamClient\GuzzleAdapter;
use YAWAF\Core\UpstreamClient\SymfonyHttpClientAdapter;
use YAWAF\Core\UpstreamClient\UpstreamClientInterface;

class TestProxy extends FilteringProxy
{
    /// @todo instead of hardcoding these, we should get their value from the same env vars which are used to drive the
    ///       client-side of the tests
    const DEFAULT_UPSTREAMS = [
        'http' => 'http://127.0.0.1/server.php',
        'tcp' => 'tcp://127.0.0.1:80',
        'unix' => 'unix:/run/nginx.server.sock',

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
            json_encode(self::ERROR_RESPONSE + ($e !== null ? ['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()] : []))
        );
    }

    /**
     * @throws \Exception
     */
    public static function getUpstream(string $scheme = 'http'): string
    {
        switch ($scheme) {
            case 'http':
                if (trim(@$_ENV['HTTPSERVER_HOST']) === '') {
                    throw new \Exception("Unsupported scheme for upstream server: $scheme");
                }
                return 'http://' . $_ENV['HTTPSERVER_HOST'] .
                    (trim(@$_ENV['HTTPSERVER_PORT']) === '' ? '' : ':' . $_ENV['HTTPSERVER_PORT']) .
                    $_ENV['HTTPSERVER_PATH'];
            /// @todo...
            //case 'https':
            //case 'tcp':
            case 'unix':
                if (trim(@$_ENV['HTTPSERVER_SOCKET']) === '') {
                    throw new \Exception("Unsupported scheme for upstream server: $scheme");
                }
                return 'unix:' . $_ENV['HTTPSERVER_SOCKET'];
            default:
                throw new \InvalidArgumentException("Unsupported scheme for upstream server: $scheme");
        }
    }

    /**
     * @throws \Exception
     */
    public static function createUpstreamClient(string $clientType, array $options = []): UpstreamClientInterface
    {
        switch ($clientType) {
            case 'guzzle':
                return new GuzzleAdapter($options);
            case 'sfhc_native':
                return new SymfonyHttpClientAdapter([UpstreamClientInterface::OPT_TRANSPORT => 'native'] + $options);
            case 'sfhc_curl':
                return new SymfonyHttpClientAdapter([UpstreamClientInterface::OPT_TRANSPORT => 'curl'] + $options);
            default:
                throw new \InvalidArgumentException("Unsupported upstream client type: '$clientType'");
        }
    }
}
