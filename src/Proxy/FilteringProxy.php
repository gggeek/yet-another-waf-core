<?php
declare(strict_types=1);

namespace YAWAF\Core\Proxy;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\RequestDenied;
use YAWAF\Core\Logger\PrivateLoggerTrait;

/**
 * Allows adding middlewares to execute logic before forwarding the request upstream / after having received the response,
 * such as e.g. a firewall component.
 * Note: what makes this a proxy really is the fact that a proper Proxy is passed in as $upstreamConnector...
 * Should we change the typehint for $upstreamConnector to eg. a specific subclass of RequestHandlerInterface?
 */
abstract class FilteringProxy implements RequestHandlerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected MiddlewareInterface $filter;
    protected RequestHandlerInterface $upstreamConnector;

    public function __construct(MiddlewareInterface $filter, RequestHandlerInterface $upstreamConnector, LoggerInterface|null $logger = null)
    {
        $this->filter = $filter;
        $this->upstreamConnector = $upstreamConnector;
        $this->logger = $logger;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $this->debug("Received request: " . $this->request2Log($request));

            $response = $this->filter->process($request, $this->upstreamConnector);
            $this->debug("Returned response: " . $this->response2Log($response));

            return $response;
        } catch (RequestDenied $e) {
            $this->debug("Request Denied Exception thrown during processing of request: " . $e->getMessage());
            return $this->deniedResponse($request, $e);
        } catch (\Throwable $e) {
            $this->error("Exception thrown during processing of request: " . $e->getMessage());
            // NB: we do not catch exceptions thrown during this function call as we would not know what to return anyway...
            return $this->errorResponse($request, $e);
        }
    }

    /**
     * Generates an "access denied" response.
     * Make sure to mimic what the upstream API returns by default for not-accepted requests - but give a specific hint
     * so that these responses can be told apart from the upstream's "access denied" ones.
     * @todo make it easy to set this response via configuration
     */
    abstract protected function deniedResponse(ServerRequestInterface $request, \Throwable|null $e = null): ResponseInterface;

    /**
     * Generates an "error happened" response.
     * Make sure to mimic correctly what the upstream API returns by default for failed requests - but give a specific hint
     * so that these responses can be told apart from the upstream's "error happened" ones.
     * @todo make it easy to set this response via configuration
     */
    abstract protected function errorResponse(ServerRequestInterface $request, \Throwable|null $e = null): ResponseInterface;

    // *** Logging ***

    protected function request2Log(ServerRequestInterface $request): string
    {
        return $request->getMethod() . ' ' . $request->getUri();
    }

    protected function response2Log(ResponseInterface $response): string
    {
        return $response->getHeaderLine();
    }
}
