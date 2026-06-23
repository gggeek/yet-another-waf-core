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
            json_encode(self::ERROR_RESPONSE + ($e !== null ? ['message' => $e->getMessage()] : []))
        );
    }

    /**
     * @throws \Exception
     */
    public static function getUpstream(string $scheme = 'http'): string
    {
        if (!isset(self::DEFAULT_UPSTREAMS[$scheme])) {
            throw new \Exception("Unsupported scheme for upstream server: $scheme");
        }
        return self::DEFAULT_UPSTREAMS[$scheme];
    }

    /**
     * @throws \Exception
     */
    public static function createUpstreamClient(string $clientType): UpstreamClientInterface
    {
        switch ($clientType) {
            case 'guzzle':
                return new GuzzleAdapter();
            case 'sfhc_native':
                return new SymfonyHttpClientAdapter([UpstreamClientInterface::OPT_TRANSPORT => 'native']);
            case 'sfhc_curl':
                return new SymfonyHttpClientAdapter([UpstreamClientInterface::OPT_TRANSPORT => 'curl']);
            default:
                throw new \Exception("Unsupported upstream client type: '$clientType'");
        }
    }
}
