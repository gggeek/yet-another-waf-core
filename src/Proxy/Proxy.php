<?php
declare(strict_types=1);

namespace YAWAF\Core\Proxy;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use YAWAF\Core\Exception\RequestDenied;
use YAWAF\Core\Exception\UpstreamRequestError;
use YAWAF\Core\Exception\UpstreamRequestTimeout;
use YAWAF\Core\Logger\PrivateLoggerTrait;
use YAWAF\Core\UpstreamClient\UpstreamClientFactory;
use YAWAF\Core\UpstreamClient\UpstreamClientInterface;

class Proxy implements RequestHandlerInterface, LoggerAwareInterface
{
    const UPSTREAM_ERROR_STATUS_CODE = 502;
    const UPSTREAM_TIMEOUT_STATUS_CODE = 504;

    use LoggerAwareTrait;
    use PrivateLoggerTrait;

    protected UpstreamClientInterface $client;
    protected array $overrideHeaders = [];
    protected array $overriddenHeaders = [];

    /**
     * @todo fold the $logger arg into the options?
     * @todo what about unifying the arrays of options for $this and for the $httpClient?
     * @todo
     * @throws \Exception
     */
    public function __construct(UpstreamClientInterface|array|null $httpClient = null, LoggerInterface|null $logger = null)
    {
        // set first the logger
        $this->logger = $logger;
        if ($httpClient === null || is_array($httpClient)) {
            $httpClient = (new UpstreamClientFactory())->createClient((array)$httpClient);
        }
        $this->client = $httpClient;
        $this->overrideHeaders['User-Agent'] = 'YAWAF Proxy HttpClient' . (
            ($cua = $this->$this->client->getUserAgent()) !== '' ? ' (' . $cua . ')' : ''
        );
    }

    /**
     * @throws RequestDenied when using a middleware-aware client, this could be thrown
     * @throws UpstreamRequestError
     * @throws UpstreamRequestTimeout
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request = $this->filterRequest($request);

/// @todo... we should follow the rules set out in https://httpwg.org/specs/rfc9112.html#rfc.section.3.2.2: use the
///          host/port from the absolute form of the uri to replace the value from Host header

        $response = $this->sendRequest($this->client, $request);

        return $this->filterResponse($response, $request);
    }

    /**
     * Aka "handleInner".
     * NB: when $client is async, this might not throw at all, but exceptions might be thrown when trying to read
     * the response body...
     *
     * @throws RequestDenied when using a middleware-aware client, this could be thrown
     * @throws UpstreamRequestError
     * @throws UpstreamRequestTimeout NB: only if a timeout was set into $client
     */
    protected function sendRequest(UpstreamClientInterface $client, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $response = $client->sendRequest($request);

            $this->debug("Upstream returned HTTP/" . $response->getProtocolVersion() . ' ' . $response->getStatusCode() . ' ' .
                $response->getReasonPhrase());
        } catch (RequestDenied $e) {
            $this->debug("Request denied before sending to upstream: " . $e->getMessage());
            throw $e;
        } catch (UpstreamRequestTimeout $e) {
            $this->debug("Timeout sending request to upstream: " . $e->getMessage());
            throw $e;
        } catch (UpstreamRequestError $e) {
            $this->debug("Error sending request to upstream: " . $e->getMessage());
            throw $e;
        } catch (NetworkExceptionInterface $e) {
            $this->debug("Network error sending request to upstream (" . get_class($e) . "): " . $e->getMessage());
            throw new UpstreamRequestError($e->getMessage(), $e->getCode(), $e);
        } catch (\Throwable $e) {
            $this->debug("Unexpected error sending request to upstream (" . get_class($e) . "): " . $e->getMessage());
            throw new UpstreamRequestError($e->getMessage(), $e->getCode(), $e);
        }

        return $response;
    }

    protected function filterRequest(ServerRequestInterface $request): ServerRequestInterface
    {
/// @todo... add x-forwarded headers and co., strip/massage hop-by-hop headers

        $this->overriddenHeaders = [];
        foreach ($this->overrideHeaders as $name => $value) {
            if ($request->hasHeader($name)) {
                $this->overriddenHeaders[$name] = $request->getHeader($name);
            }
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    protected function filterResponse(ResponseInterface $response, ServerRequestInterface $request): ResponseInterface
    {
        return $response;
    }
}
