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
 * @todo rename, or tweak the API a bit?
 *       even though the proxy is passed in the connector to upstream as a RequestHandlerInterface, it does not make a
 *       lot of sense to make it a Middleware, as it can not be hooked onto _any kind_ of RequestHandlerInterface.
 *       Exposing both interfaces is not a good idea as it would leave users wondering about intended usage.
 *       In fact, it might make more sense rename the UpstreamConnector class `Proxy`, rename this to something
 *       different (what? it is a middleware-cum-filters-and-logging...) and possibly make this a Middleware...
 *       Also, rename $this->filter to Firewall?
 */
abstract class Server implements RequestHandlerInterface, LoggerAwareInterface
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
