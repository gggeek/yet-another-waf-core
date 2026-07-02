<?php
declare(strict_types=1);

namespace YAWAF\Core\Server;

use Nyholm\Psr7\Stream;
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
 * Allows adding middlewares to execute logic before forwarding the request / after having received the response,
 * such as e.g. a firewall middleware component.
 */
abstract class MiddlewareAware implements RequestHandlerInterface, LoggerAwareInterface
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
        } catch (RequestDenied $e) {
            $this->debug("Request Denied Exception thrown during processing of request" . (($msg = $e->getMessage()) !== '' ? (': ' . $msg) : ''));
            $response = $this->deniedResponse($request, $e);
        } catch (\Throwable $e) {
            $this->error("Exception thrown during processing of request" . (($msg = $e->getMessage()) !== '' ? (': ' . $msg) : ''));
            // NB: we do not catch exceptions thrown during this function call as we would not know what to return anyway...
            $response = $this->errorResponse($request, $e);
        }

        // We should never send a body back to HEAD requests. Be lenient of upstreams and access denied errors
        // Hopefully this does not modify the content-type header...
        /// @todo we should move this to a 'drop-body-for-head-responses' middleware (or maybe to the upstreamConnector)?
        if ($request->getMethod() === 'HEAD') {
            /// @todo we could log a warning if upstream sent a body, but that would force us to read it fully, so
            ///       we don't do that to save resources
            $response = $response->withBody(Stream::create(''));
        }

        return $response;
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
        return $response->getStatusCode() . ' ' . $response->getReasonPhrase();
    }
}
