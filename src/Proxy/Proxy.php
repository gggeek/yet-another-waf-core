<?php
declare(strict_types=1);

namespace YAWAF\Core\Proxy;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\UpstreamClient\UpstreamClientFactory;
use YAWAF\Core\UpstreamClient\UpstreamClientInterface;

/**
 * @todo split this into a subclass that does all the handling not related to using a fixed upstream, and a descendent
 *       class which adds `setUpstream` and the corresponding logic
 */
class Proxy implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected UpstreamClientInterface $client;

    /**
     * @throws \Exception
     */
    public function __construct(UpstreamClientInterface|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        if (!$httpClient) {
            $httpClient = (new UpstreamClientFactory())->createClient();
        }
        $this->client = $httpClient;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws ClientExceptionInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->withProxyHeaders($request, $this->client);

/// @todo... we should follow the rules set out in https://httpwg.org/specs/rfc9112.html#rfc.section.3.2.2: use the
///          host/port from the absolute form of the uri to replace the value from Host header

        $response = $this->client->sendRequest($request);
        $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
            $response->getReasonPhrase());
        return $response;
    }

    protected function withProxyHeaders(ServerRequestInterface $request, ClientInterface $client)
    {
/// @todo... add x-forwarded headers and co., strip/massage hop-by-hop headers (use a dedicated function)

        return $request;
    }
}
